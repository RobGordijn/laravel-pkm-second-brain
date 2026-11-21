<?php

namespace App\Models;

use App\Enums\LinkState;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\Pivot;
use Illuminate\Support\Carbon;

/**
 * A link between two notes. Links are always stored in both directions,
 * so each note can list its related notes with a plain belongsToMany.
 *
 * @property int $item_id
 * @property int $related_item_id
 * @property float $similarity
 * @property ?string $reason
 * @property LinkState $state
 */
class ItemLink extends Pivot
{
    protected $table = 'item_links';

    public $incrementing = true;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'similarity' => 'float',
            'state' => LinkState::class,
        ];
    }

    public function isSuggested(): bool
    {
        return $this->state === LinkState::Suggested;
    }

    /**
     * Links in either direction between the two notes.
     *
     * @param  Builder<ItemLink>  $query
     */
    public function scopeBetween(Builder $query, Item $item, Item $related): void
    {
        $query->where(fn (Builder $query) => $query
            ->where(['item_id' => $item->id, 'related_item_id' => $related->id])
            ->orWhere(fn (Builder $query) => $query->where(['item_id' => $related->id, 'related_item_id' => $item->id])));
    }

    /**
     * Links in either direction that involve the note.
     *
     * @param  Builder<ItemLink>  $query
     */
    public function scopeInvolving(Builder $query, Item $item): void
    {
        $query->where(fn (Builder $query) => $query->where('item_id', $item->id)->orWhere('related_item_id', $item->id));
    }

    /**
     * Confirm or reject the link between two notes, in both directions.
     */
    public static function judge(Item $item, Item $related, LinkState $state): void
    {
        static::query()->between($item, $related)->update(['state' => $state, 'updated_at' => now()]);
    }

    /**
     * Store links from the note to each related note and back, skipping pairs that already exist.
     *
     * @param  array<int, array{similarity: float, reason: ?string, state: LinkState, reverse_reason?: ?string}>  $links  Keyed by related item id.
     */
    public static function storeBothWays(Item $item, array $links): void
    {
        $now = Carbon::now();

        $rows = collect($links)->flatMap(fn (array $link, int $relatedId) => [
            static::row($item->id, $relatedId, $link['similarity'], $link['reason'], $link['state'], $now),
            static::row($relatedId, $item->id, $link['similarity'], $link['reverse_reason'] ?? $link['reason'], $link['state'], $now),
        ]);

        static::query()->insertOrIgnore($rows->values()->all());
    }

    /**
     * @return array<string, mixed>
     */
    private static function row(int $itemId, int $relatedId, float $similarity, ?string $reason, LinkState $state, Carbon $now): array
    {
        return [
            'item_id' => $itemId,
            'related_item_id' => $relatedId,
            'similarity' => $similarity,
            'reason' => $reason,
            'state' => $state->value,
            'created_at' => $now,
            'updated_at' => $now,
        ];
    }
}
