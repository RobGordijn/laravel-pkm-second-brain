<?php

namespace App\Http\Controllers;

use App\Ai\Agents\InsightDistiller;
use App\Listeners\LogAiRequests;
use App\Models\Item;
use Illuminate\Http\RedirectResponse;
use Laravel\Ai\Exceptions\AiException;

class ItemInsightSuggestionController extends Controller
{
    /**
     * Ask the AI for a first draft of the key insight; the author edits and saves it.
     */
    public function __invoke(Item $item): RedirectResponse
    {
        LogAiRequests::forItem($item->id);

        try {
            $suggestion = (new InsightDistiller)->distill($item);
        } catch (AiException $exception) {
            report($exception);

            return to_route('items.show', $item)->withErrors(['key_insight' => 'The AI is unreachable or overloaded right now. Please try again shortly.']);
        }

        return to_route('items.show', $item)->withInput([
            'key_insight' => $suggestion,
        ]);
    }
}
