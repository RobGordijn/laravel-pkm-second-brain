<?php

namespace App\Services;

use App\Models\Item;
use App\Models\ItemAction;
use App\Models\Project;
use App\Models\Tag;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Str;
use RuntimeException;
use ZipArchive;

/**
 * Writes the whole brain to a zip of plain markdown files that open as a vault in Obsidian or any editor:
 * one file per note in notes/, one per project or area in projects/, all linked with [[...]].
 */
class MarkdownExport
{
    public function __construct(private WikiLinks $wikiLinks) {}

    /**
     * Build the zip and return its path; the caller deletes it after sending.
     */
    public function toZip(): string
    {
        $path = tempnam(sys_get_temp_dir(), 'brain-export-');
        $zip = new ZipArchive;

        if ($zip->open($path, ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('Could not create the export file.');
        }

        Item::query()
            ->with(['tags', 'projects', 'actions', 'relatedItems', 'mentionedItems'])
            ->lazyById(100)
            ->each(fn (Item $item) => $zip->addFromString('notes/'.$this->fileName($item).'.md', $this->note($item)));

        Project::query()
            ->with(['items' => fn (BelongsToMany $query) => $query->select(['items.id', 'items.title', 'items.raw'])])
            ->each(fn (Project $project) => $zip->addFromString("projects/{$project->slug}.md", $this->project($project)));

        $zip->close();

        return $path;
    }

    /**
     * A stable file name per note: the id keeps it unique, the title keeps it readable.
     */
    public function fileName(Item $item): string
    {
        return $item->id.'-'.(Str::slug(Str::limit($this->title($item), 60, '')) ?: 'note');
    }

    public function note(Item $item): string
    {
        $frontMatter = $this->frontMatter([
            'id' => $item->id,
            'title' => $this->title($item),
            'type' => $item->type?->value,
            'status' => $item->status->value,
            'authored' => $item->isAuthored() ?: null,
            'tags' => $item->tags->map(fn (Tag $tag) => $tag->slug)->all(),
            'projects' => $item->projects->map(fn (Project $project) => "[[{$project->slug}]]")->all(),
            'url' => $item->url,
            'summary' => $item->summary,
            'key_insight' => $item->key_insight,
            'why_saved' => $item->why_saved,
            'clarification_question' => $item->clarification_answer ? $item->clarification_question : null,
            'clarification_answer' => $item->clarification_answer,
            'created' => $item->created_at?->toIso8601String(),
            'updated' => $item->updated_at?->toIso8601String(),
            'archived' => $item->archived_at?->toIso8601String(),
        ]);

        $sections = ['# '.$this->title($item), $this->vaultLinks($item->body ?? $item->raw)];

        if ($item->actions->isNotEmpty()) {
            $sections[] = "## Actions\n\n".$item->actions
                ->map(fn (ItemAction $action) => '- ['.($action->done_at ? 'x' : ' ').'] '.$action->text.($action->due_on ? " (due {$action->due_on->toDateString()})" : ''))
                ->implode("\n");
        }

        $confirmed = $item->relatedItems->reject(fn (Item $related) => $related->pivot->isSuggested());

        if ($confirmed->isNotEmpty()) {
            $sections[] = "## Related\n\n".$confirmed
                ->map(fn (Item $related) => '- '.$this->vaultLink($related).($related->pivot->reason ? " — {$related->pivot->reason}" : ''))
                ->implode("\n");
        }

        if ($item->body !== null && trim($item->body) !== trim($item->raw)) {
            $sections[] = "## Original dump\n\n".collect(explode("\n", trim($item->raw)))->map(fn (string $line) => rtrim("> {$line}"))->implode("\n");
        }

        return $frontMatter.implode("\n\n", array_filter($sections, 'filled'))."\n";
    }

    public function project(Project $project): string
    {
        $frontMatter = $this->frontMatter([
            'name' => $project->name,
            'kind' => $project->kind->value,
            'goal' => $project->goal,
            'due_on' => $project->due_on?->toDateString(),
            'archived' => $project->archived_at?->toIso8601String(),
        ]);

        $notes = $project->items
            ->sortBy(fn (Item $item) => Str::lower($this->title($item)))
            ->map(fn (Item $item) => '- '.$this->vaultLink($item))
            ->implode("\n");

        return $frontMatter.'# '.$project->name."\n\n".($project->goal ? "{$project->goal}\n\n" : '').($notes !== '' ? "## Notes\n\n{$notes}\n" : '');
    }

    /**
     * Point [[...]] links and [#12] references at the exported note files.
     */
    private function vaultLinks(string $text): string
    {
        $text = $this->wikiLinks->replace($text, fn (Item $item, string $label) => $this->vaultLink($item, $label));

        return preg_replace_callback('/\[#(\d+)\]/', function (array $match) {
            $item = Item::query()->select(['id', 'title', 'raw'])->find((int) $match[1]);

            return $item ? $this->vaultLink($item, "#{$item->id}") : $match[0];
        }, $text) ?? $text;
    }

    private function vaultLink(Item $item, ?string $label = null): string
    {
        $label = str_replace(['[', ']', '|'], '', $label ?? $this->title($item));

        return '[['.$this->fileName($item).'|'.$label.']]';
    }

    private function title(Item $item): string
    {
        return $item->title ?? Str::limit(Str::squish($item->raw), 60);
    }

    /**
     * YAML front matter; scalars are written as JSON strings, which YAML reads as-is.
     *
     * @param  array<string, mixed>  $fields
     */
    private function frontMatter(array $fields): string
    {
        $lines = collect($fields)
            ->reject(fn (mixed $value) => $value === null || $value === '' || $value === [])
            ->map(fn (mixed $value, string $key) => is_array($value)
                ? "{$key}:\n".collect($value)->map(fn (mixed $entry) => '  - '.$this->yamlScalar($entry))->implode("\n")
                : "{$key}: ".$this->yamlScalar($value));

        return "---\n".$lines->implode("\n")."\n---\n\n";
    }

    private function yamlScalar(mixed $value): string
    {
        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }

        return is_int($value) ? (string) $value : json_encode((string) $value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
}
