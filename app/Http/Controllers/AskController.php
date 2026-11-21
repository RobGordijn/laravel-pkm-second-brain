<?php

namespace App\Http\Controllers;

use App\Ai\Agents\BrainAssistant;
use App\Http\Requests\AskRequest;
use App\Models\Item;
use App\Services\HybridSearch;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;
use Laravel\Ai\Exceptions\AiException;

class AskController extends Controller
{
    /**
     * Maximum number of notes given to the assistant as context.
     */
    private const CONTEXT_SIZE = 8;

    public function index(): View
    {
        return view('ask', ['question' => null, 'answer' => null, 'sources' => new Collection]);
    }

    /**
     * Retrieve the most relevant notes and let the assistant answer from them.
     */
    public function store(AskRequest $request, HybridSearch $search): View|RedirectResponse
    {
        $question = $request->validated('question');

        $sources = $search->search(
            $question,
            Item::query()->processed()->with(['tags', 'actions']),
            limit: self::CONTEXT_SIZE,
        );

        try {
            $answer = $sources->isEmpty()
                ? 'I found no notes about this question.'
                : (new BrainAssistant)->answer($question, $sources);
        } catch (AiException $exception) {
            report($exception);

            return back()->withInput()->withErrors(['question' => 'The AI is unreachable or overloaded right now. Please try again shortly.']);
        }

        preg_match_all('/#(\d+)/', $answer, $citations);
        Item::recordUseOf(array_values(array_intersect($sources->modelKeys(), array_map('intval', $citations[1]))));

        return view('ask', [
            'question' => $question,
            'answer' => $answer,
            'sources' => $sources,
        ]);
    }
}
