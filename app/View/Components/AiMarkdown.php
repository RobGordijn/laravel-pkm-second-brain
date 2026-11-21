<?php

namespace App\View\Components;

use App\Models\Item;
use App\Services\WikiLinks;
use Closure;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Str;
use Illuminate\View\Component;

/**
 * Renders note markdown safely: [[...]] wiki-links and [#12] or [#12, #13] references become links to existing notes.
 */
class AiMarkdown extends Component
{
    public function __construct(public string $text) {}

    public function html(): string
    {
        $html = Str::markdown(app(WikiLinks::class)->toMarkdownLinks($this->text), ['html_input' => 'strip', 'allow_unsafe_links' => false]);

        $pattern = '/\[(#\d+(?:\s*,\s*#?\d+)*)\]/';

        preg_match_all('/\d+/', implode(' ', preg_match_all($pattern, $html, $matches) ? $matches[1] : []), $ids);
        $existingIds = Item::query()->whereKey(array_map('intval', $ids[0]))->pluck('id')->all();

        return preg_replace_callback($pattern, function (array $match) use ($existingIds) {
            preg_match_all('/\d+/', $match[1], $refs);

            return collect($refs[0])
                ->map(fn (string $id) => in_array((int) $id, $existingIds, true)
                    ? '<a href="'.e(route('items.show', (int) $id)).'" class="note-ref">#'.$id.'</a>'
                    : e("[#{$id}]"))
                ->implode(' ');
        }, $html) ?? $html;
    }

    /**
     * Get the view / contents that represent the component.
     */
    public function render(): View|Closure|string
    {
        return view('components.ai-markdown');
    }
}
