<?php

namespace App\Models;

use Database\Factories\TagFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

#[Fillable(['name', 'slug'])]
class Tag extends Model
{
    /** @use HasFactory<TagFactory> */
    use HasFactory;

    /**
     * @return BelongsToMany<Item, $this>
     */
    public function items(): BelongsToMany
    {
        return $this->belongsToMany(Item::class)->using(SourcedPivot::class)->withPivot('source');
    }

    /**
     * Move all items of this tag to the target tag and delete this tag.
     */
    public function mergeInto(Tag $target): void
    {
        if ($target->is($this)) {
            return;
        }

        DB::transaction(function () use ($target) {
            DB::statement(
                'INSERT INTO item_tag (item_id, tag_id, source) SELECT item_id, ?, source FROM item_tag WHERE tag_id = ? ON CONFLICT DO NOTHING',
                [$target->id, $this->id],
            );

            $this->delete();
        });
    }

    /**
     * Find a tag by its normalized name or create it.
     */
    public static function findOrCreateByName(string $name): self
    {
        $name = Str::of($name)->squish()->lower()->ltrim('#')->toString();

        return static::firstOrCreate(['slug' => Str::slug($name)], ['name' => $name]);
    }
}
