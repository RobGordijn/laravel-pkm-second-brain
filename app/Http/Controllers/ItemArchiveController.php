<?php

namespace App\Http\Controllers;

use App\Models\Item;
use Illuminate\Http\RedirectResponse;

class ItemArchiveController extends Controller
{
    /**
     * Move a note out of the way without deleting it; it stays findable with the archive filter.
     */
    public function store(Item $item): RedirectResponse
    {
        $item->update(['archived_at' => now(), 'reviewed_at' => $item->reviewed_at ?? now()]);

        return back()->with('status', 'Archived.');
    }

    public function destroy(Item $item): RedirectResponse
    {
        $item->update(['archived_at' => null]);

        return back()->with('status', 'Restored from the archive.');
    }
}
