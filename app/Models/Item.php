<?php

namespace App\Models;

use App\Enums\ItemStatus;
use App\Enums\ItemType;
use App\Enums\LinkState;
use App\Jobs\ProcessItem;
use App\Services\WikiLinks;
use Database\Factories\ItemFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\AsVector;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

#[Fillable([
    'raw', 'why_saved', 'is_authored', 'url', 'source_title', 'source_text', 'source_fetched_at', 'type', 'type_confidence', 'title', 'summary', 'key_insight', 'body', 'status',
    'clarification_question', 'clarification_answer', 'error', 'embedding', 'processed_at', 'archived_at', 'reviewed_at',
])]
#[Hidden(['embedding', 'search_vector'])]
class Item extends Model
{
    /** @use HasFactory<ItemFactory> */
    use HasFactory;

    protected static function booted(): void
    {
        static::created(function (Item $item) {
            $item->syncMentions();
            $item->resyncNotesMentioning();
        });

        static::updated(function (Item $item) {
            if ($item->wasChanged(['raw', 'body'])) {
                $item->syncMentions();
            }

            if ($item->wasChanged('title')) {
                $item->resyncNotesMentioning();
            }
        });
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => ItemStatus::class,
            'is_authored' => 'boolean',
            'type' => ItemType::class,
            'type_confidence' => 'float',
            'embedding' => AsVector::class,
            'processed_at' => 'datetime',
            'archived_at' => 'datetime',
            'reviewed_at' => 'datetime',
            'use_count' => 'integer',
            'last_used_at' => 'datetime',
            'source_fetched_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsToMany<Tag, $this>
     */
    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(Tag::class)->using(SourcedPivot::class)->withPivot('source')->orderBy('name');
    }

    /**
     * The projects and areas this note is useful for.
     *
     * @return BelongsToMany<Project, $this>
     */
    public function projects(): BelongsToMany
    {
        return $this->belongsToMany(Project::class)->using(SourcedPivot::class)->withPivot('source')->orderBy('name');
    }

    /**
     * @return HasMany<ItemAction, $this>
     */
    public function actions(): HasMany
    {
        return $this->hasMany(ItemAction::class)->orderBy('id');
    }

    /**
     * Items this item was automatically or manually linked to.
     *
     * @return BelongsToMany<Item, $this>
     */
    public function relatedItems(): BelongsToMany
    {
        return $this->belongsToMany(Item::class, 'item_links', 'item_id', 'related_item_id')
            ->using(ItemLink::class)
            ->withPivot(['similarity', 'reason', 'state'])
            ->wherePivot('state', '!=', LinkState::Rejected)
            ->orderByPivot('similarity', 'desc');
    }

    /**
     * Notes this note links to by hand with [[...]].
     *
     * @return BelongsToMany<Item, $this>
     */
    public function mentionedItems(): BelongsToMany
    {
        return $this->belongsToMany(Item::class, 'item_mentions', 'item_id', 'mentioned_item_id')->withTimestamps();
    }

    /**
     * Backlinks: notes that link to this note by hand with [[...]].
     *
     * @return BelongsToMany<Item, $this>
     */
    public function mentionedBy(): BelongsToMany
    {
        return $this->belongsToMany(Item::class, 'item_mentions', 'mentioned_item_id', 'item_id')->withTimestamps();
    }

    /**
     * Keep the stored [[...]] links in step with the note's text.
     */
    public function syncMentions(): void
    {
        $this->mentionedItems()->sync(app(WikiLinks::class)->linkedIds($this));
    }

    /**
     * Re-resolve the notes that link, or might link, to this note after its title changed.
     */
    private function resyncNotesMentioning(): void
    {
        $pattern = filled($this->title) ? '%[['.addcslashes(trim($this->title), '%_\\').'%' : null;

        static::query()
            ->whereKeyNot($this->id)
            ->where(fn (Builder $query) => $query
                ->whereHas('mentionedItems', fn (Builder $mentioned) => $mentioned->whereKey($this->id))
                ->when($pattern, fn (Builder $query, string $pattern) => $query
                    ->orWhere('raw', 'ilike', $pattern)
                    ->orWhere('body', 'ilike', $pattern)))
            ->get(['id', 'raw', 'body'])
            ->each(fn (Item $item) => $item->syncMentions());
    }

    /**
     * Store a raw dump, with the author's optional reason for keeping it, and queue it for AI processing.
     */
    public static function capture(string $raw, ?string $whySaved = null): self
    {
        preg_match('~https?://[^\s<>"\']+~i', $raw, $matches);

        $item = static::create([
            'raw' => trim($raw),
            'why_saved' => filled($whySaved) ? trim($whySaved) : null,
            'url' => isset($matches[0]) ? rtrim($matches[0], '.,);') : null,
            'status' => ItemStatus::Pending,
        ]);

        ProcessItem::dispatch($item);

        return $item;
    }

    /**
     * Store a note the author wrote themselves; the AI adds tags and links but leaves the text alone.
     */
    public static function write(string $body, ?string $title = null): self
    {
        $item = static::create([
            'raw' => trim($body),
            'body' => trim($body),
            'title' => filled($title) ? Str::squish($title) : null,
            'is_authored' => true,
            'status' => ItemStatus::Pending,
        ]);

        ProcessItem::dispatch($item);

        return $item;
    }

    /**
     * Save the author's own text (an autosave or the final save) and let the AI catch up once writing pauses.
     */
    public function saveWriting(string $body, ?string $title = null): void
    {
        $this->update([
            'body' => trim($body),
            'title' => filled($title) ? Str::squish($title) : $this->title,
            'is_authored' => $this->is_authored || trim($body) !== trim((string) $this->body),
        ]);

        if ($this->wasChanged(['body', 'title', 'is_authored'])) {
            ProcessItem::dispatch($this)->delay(now()->addSeconds(config('pkm.write_processing_delay')));
        }
    }

    /**
     * Let the AI rewrite the note again. It starts from the current text, so the author's edits
     * are not lost; that text becomes the new original dump.
     */
    public function releaseToAi(): void
    {
        $this->update([
            'raw' => filled($this->body) ? $this->body : $this->raw,
            'is_authored' => false,
        ]);

        $this->reprocess();
    }

    /**
     * Whether the author wrote or rewrote the text, so the AI must not replace it.
     */
    public function isAuthored(): bool
    {
        return $this->is_authored;
    }

    /**
     * Put the item back in the queue, e.g. after a failure or a clarification.
     */
    public function reprocess(): void
    {
        $this->update(['status' => ItemStatus::Pending, 'error' => null]);

        ProcessItem::dispatch($this);
    }

    /**
     * The text used to generate the item's embedding.
     */
    public function embeddingText(): string
    {
        return collect([
            $this->title,
            $this->key_insight,
            $this->why_saved,
            $this->source_title,
            $this->summary,
            $this->body ?? $this->raw,
            $this->clarification_answer,
            $this->tags->pluck('name')->implode(', '),
        ])->filter()->implode("\n\n");
    }

    /**
     * The text sent to the AI for analysis, including any clarification the author gave.
     */
    public function promptText(): string
    {
        $text = $this->isAuthored() ? ($this->body ?? $this->raw) : $this->raw;

        if (filled($this->why_saved)) {
            $text .= "\n\n---\nWhy the author saved this / where they left off: {$this->why_saved}";
        }

        if (filled($this->source_text)) {
            $text .= "\n\n---\nContent of the linked page".($this->source_title ? " ({$this->source_title})" : '').":\n{$this->source_text}";
        }

        if ($this->clarification_question && $this->clarification_answer) {
            $text .= "\n\n---\nQuestion: {$this->clarification_question}\nAuthor's answer: {$this->clarification_answer}";
        }

        return $text;
    }

    /**
     * Count a moment the author actually used this note (opened it, or an answer cited it),
     * without touching updated_at.
     */
    public function recordUse(): void
    {
        static::recordUseOf([$this->id]);
        $this->use_count++;
        $this->last_used_at = now();
    }

    /**
     * @param  array<int, int>  $ids
     */
    public static function recordUseOf(array $ids): void
    {
        if ($ids === []) {
            return;
        }

        static::query()->whereKey($ids)->toBase()->update([
            'use_count' => DB::raw('use_count + 1'),
            'last_used_at' => now(),
        ]);
    }

    /**
     * Progressive summarization: a note that keeps coming back deserves a sharper top layer.
     */
    public function deservesKeyInsight(): bool
    {
        return blank($this->key_insight) && $this->use_count >= config('pkm.distill_after_uses');
    }

    /**
     * Notes that keep coming back but have no key insight yet; the query counterpart of deservesKeyInsight().
     *
     * @param  Builder<Item>  $query
     */
    public function scopeAwaitingDistillation(Builder $query): void
    {
        $query->whereNull('key_insight')->where('use_count', '>=', config('pkm.distill_after_uses'));
    }

    public function isArchived(): bool
    {
        return $this->archived_at !== null;
    }

    /**
     * @param  Builder<Item>  $query
     */
    public function scopeUnarchived(Builder $query): void
    {
        $query->whereNull('archived_at');
    }

    /**
     * Notes the AI pipeline has finished with, including those waiting for the author's answer.
     *
     * @param  Builder<Item>  $query
     */
    public function scopeProcessed(Builder $query): void
    {
        $query->whereIn('status', [ItemStatus::Ready, ItemStatus::NeedsFeedback]);
    }

    /**
     * @param  Builder<Item>  $query
     */
    public function scopeNeedsFeedback(Builder $query): void
    {
        $query->where('status', ItemStatus::NeedsFeedback);
    }

    /**
     * Processed notes the author has not yet placed during a weekly review.
     *
     * @param  Builder<Item>  $query
     */
    public function scopeAwaitingReview(Builder $query): void
    {
        $query->unarchived()->processed()->whereNull('reviewed_at');
    }

    /**
     * @param  Builder<Item>  $query
     */
    public function scopeFullTextSearch(Builder $query, string $terms): void
    {
        $query->whereRaw("search_vector @@ websearch_to_tsquery('simple', ?)", [$terms]);
    }
}
