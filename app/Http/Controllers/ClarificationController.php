<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreClarificationRequest;
use App\Models\Item;
use Illuminate\Http\RedirectResponse;

class ClarificationController extends Controller
{
    /**
     * Store the author's answer and run the item through the pipeline again.
     */
    public function store(StoreClarificationRequest $request, Item $item): RedirectResponse
    {
        $item->update(['clarification_answer' => $request->validated('answer')]);
        $item->reprocess();

        return back()->with('status', 'Thanks, the item is being processed again.');
    }
}
