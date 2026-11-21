<?php

namespace App\Http\Controllers;

use App\Models\Item;
use Illuminate\Http\RedirectResponse;

class ItemAuthorshipController extends Controller
{
    /**
     * Hand a note the author wrote back to the AI, which rewrites it from the current text.
     */
    public function destroy(Item $item): RedirectResponse
    {
        $item->releaseToAi();

        return back()->with('status', 'The AI is rewriting the note from your current text.');
    }
}
