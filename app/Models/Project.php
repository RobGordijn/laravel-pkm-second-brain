<?php

namespace App\Models;

use App\Enums\ProjectKind;
use Database\Factories\ProjectFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * A project or area (the P and A of PARA) that notes are organised under by what they are useful for.
 */
#[Fillable(['name', 'slug', 'kind', 'goal', 'due_on', 'archived_at'])]
class Project extends Model
{
    /** @use HasFactory<ProjectFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'kind' => ProjectKind::class,
            'due_on' => 'date',
            'archived_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (Project $project) {
            $project->name = Str::squish($project->name);
            $project->slug = Str::slug($project->name);
        });
    }

    /**
     * @return BelongsToMany<Item, $this>
     */
    public function items(): BelongsToMany
    {
        return $this->belongsToMany(Item::class)->using(SourcedPivot::class)->withPivot('source');
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    /**
     * @param  Builder<Project>  $query
     */
    public function scopeActive(Builder $query): void
    {
        $query->whereNull('archived_at');
    }

    public function isArchived(): bool
    {
        return $this->archived_at !== null;
    }

    /**
     * Archive the project together with its notes that no other active project or area still needs.
     * The notes get the same timestamp, so restoring brings back exactly what was archived here.
     */
    public function archive(): void
    {
        DB::transaction(function () {
            $archivedAt = now();

            $this->update(['archived_at' => $archivedAt]);

            $itemIds = $this->items()
                ->whereNull('items.archived_at')
                ->whereDoesntHave('projects', fn (Builder $projects) => $projects->active())
                ->pluck('items.id');

            Item::query()->whereKey($itemIds)->update(['archived_at' => $archivedAt]);
        });
    }

    /**
     * Make the project active again and restore the notes that were archived along with it.
     */
    public function restore(): void
    {
        if (! $this->isArchived()) {
            return;
        }

        DB::transaction(function () {
            $itemIds = $this->items()->where('items.archived_at', $this->archived_at)->pluck('items.id');

            Item::query()->whereKey($itemIds)->update(['archived_at' => null]);

            $this->update(['archived_at' => null]);
        });
    }
}
