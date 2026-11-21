<?php

namespace App\Services;

use App\Models\Item;
use Closure;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Links the author writes by hand between notes: [[Title]], [[#12]] or [[#12|custom text]].
 *
 * A title matches case-insensitively; when two notes share a title the oldest one wins.
 * The #id form keeps working when the AI later renames the target note.
 */
class WikiLinks
{
    public const PATTERN = '/\[\[\s*([^\[\]|\n]+?)\s*(?:\|\s*([^\[\]\n]+?)\s*)?\]\]/u';

    /**
     * Resolve every wiki-link target in the texts to an existing note.
     *
     * @param  array<int, ?string>  $texts
     * @return array<string, Item> Keyed by normalised target ("#12" or a lowercased title).
     */
    public function resolve(array $texts): array
    {
        $targets = collect($texts)
            ->filter()
            ->flatMap(fn (string $text) => preg_match_all(self::PATTERN, $text, $matches) ? $matches[1] : [])
            ->map(fn (string $target) => $this->normalise($target))
            ->unique();

        [$idTargets, $titleTargets] = $targets->partition(fn (string $target) => $this->isIdTarget($target));
        $resolved = [];

        if ($idTargets->isNotEmpty()) {
            Item::query()
                ->select(['id', 'title', 'raw'])
                ->whereKey($idTargets->map(fn (string $target) => (int) substr($target, 1))->values()->all())
                ->get()
                ->each(function (Item $item) use (&$resolved) {
                    $resolved['#'.$item->id] = $item;
                });
        }

        if ($titleTargets->isNotEmpty()) {
            Item::query()
                ->select(['id', 'title', 'raw'])
                ->whereIn(DB::raw('lower(title)'), $titleTargets->values()->all())
                ->orderBy('id')
                ->get()
                ->each(function (Item $item) use (&$resolved) {
                    $resolved[$this->normalise($item->title)] ??= $item;
                });
        }

        return $resolved;
    }

    /**
     * Ids of the notes this note links to by hand, excluding itself.
     *
     * @return list<int>
     */
    public function linkedIds(Item $item): array
    {
        return collect($this->resolve([$item->raw, $item->body]))
            ->map(fn (Item $linked) => $linked->id)
            ->reject(fn (int $id) => $id === $item->id)
            ->unique()
            ->values()
            ->all();
    }

    /**
     * Replace each resolvable wiki-link with the output of $link; unresolved links stay as written.
     *
     * @param  Closure(Item, string): string  $link  Receives the target note and the label to show.
     */
    public function replace(string $text, Closure $link): string
    {
        $resolved = $this->resolve([$text]);

        return preg_replace_callback(self::PATTERN, function (array $match) use ($resolved, $link) {
            $target = $this->normalise($match[1]);
            $item = $resolved[$target] ?? null;

            if ($item === null) {
                return $match[0];
            }

            $label = filled($match[2] ?? null)
                ? $match[2]
                : ($this->isIdTarget($target) ? ($item->title ?? $match[1]) : $match[1]);

            return $link($item, $label);
        }, $text) ?? $text;
    }

    /**
     * Turn wiki-links into markdown links to the notes in this app.
     */
    public function toMarkdownLinks(string $text): string
    {
        return $this->replace($text, fn (Item $item, string $label) => '['.addcslashes($label, '[]\\*_`').']('.route('items.show', $item).')');
    }

    private function normalise(string $target): string
    {
        return Str::lower(Str::squish($target));
    }

    private function isIdTarget(string $target): bool
    {
        return preg_match('/^#\d+$/', $target) === 1;
    }
}
