<?php

namespace App\Http\Controllers;

use App\Models\Tag;
use Illuminate\Http\Request;
use Illuminate\View\View;

class TagController extends Controller
{
    /**
     * All tags with usage counts, plus any pending AI merge proposals.
     */
    public function index(Request $request): View
    {
        $tags = Tag::query()->withCount('items')->orderBy('name')->get();
        $tagsById = $tags->keyBy('id');

        $suggestions = collect($request->session()->get('tag_suggestions', []))
            ->map(fn (array $suggestion) => [
                'target' => $tagsById->get($suggestion['target_id']),
                'sources' => collect($suggestion['source_ids'])->map(fn (int $id) => $tagsById->get($id))->filter()->values(),
                'reason' => $suggestion['reason'],
            ])
            ->filter(fn (array $suggestion) => $suggestion['target'] && $suggestion['sources']->isNotEmpty())
            ->values();

        return view('tags.index', ['tags' => $tags, 'suggestions' => $suggestions]);
    }
}
