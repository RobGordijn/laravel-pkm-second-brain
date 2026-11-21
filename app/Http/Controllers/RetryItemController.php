<?php

namespace App\Http\Controllers;

use App\Models\Item;
use Illuminate\Http\RedirectResponse;

class RetryItemController extends Controller
{
    /**
     * Run the item through the AI pipeline again.
     */
    public function __invoke(Item $item): RedirectResponse
    {
        $item->reprocess();

        return back()->with('status', 'Queued again.');
    }
}
