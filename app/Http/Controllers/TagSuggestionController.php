<?php

namespace App\Http\Controllers;

use App\Ai\Agents\TagCurator;
use App\Models\Tag;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Laravel\Ai\Exceptions\AiException;

class TagSuggestionController extends Controller
{
    /**
     * Ask the AI which tags are duplicates and keep the proposals in the session for review.
     */
    public function store(Request $request): RedirectResponse
    {
        $tags = Tag::query()->withCount('items')->orderByDesc('items_count')->get();

        if ($tags->count() < 2) {
            return to_route('tags.index')->with('status', 'There are too few tags to tidy up yet.');
        }

        try {
            $suggestions = (new TagCurator)->proposeMerges($tags);
        } catch (AiException $exception) {
            report($exception);

            return to_route('tags.index')->with('status', 'The AI is unreachable or overloaded right now. Please try again shortly.');
        }

        $request->session()->put('tag_suggestions', $suggestions);

        return to_route('tags.index')->with(
            'status',
            match (count($suggestions)) {
                0 => 'No duplicate tags found.',
                1 => '1 suggestion to merge tags.',
                default => count($suggestions).' suggestions to merge tags.',
            },
        );
    }
}
