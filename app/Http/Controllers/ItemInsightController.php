<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateItemInsightRequest;
use App\Jobs\LinkItem;
use App\Models\Item;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Str;

class ItemInsightController extends Controller
{
    /**
     * Save the key insight in the author's words; an empty value removes it.
     * The note is re-embedded, since the insight is now part of what it is about.
     */
    public function update(UpdateItemInsightRequest $request, Item $item): RedirectResponse
    {
        $insight = Str::squish((string) $request->validated('key_insight'));

        $item->update(['key_insight' => $insight === '' ? null : $insight]);

        LinkItem::dispatch($item);

        return to_route('items.show', $item)->with('status', $insight === '' ? 'Key insight removed.' : 'Key insight saved.');
    }
}
