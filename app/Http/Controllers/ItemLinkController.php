<?php

namespace App\Http\Controllers;

use App\Enums\LinkState;
use App\Http\Requests\UpdateItemLinkRequest;
use App\Models\Item;
use App\Models\ItemLink;
use Illuminate\Http\RedirectResponse;

class ItemLinkController extends Controller
{
    /**
     * Confirm or reject a suggested link, in both directions.
     */
    public function update(UpdateItemLinkRequest $request, Item $item, Item $related): RedirectResponse
    {
        ItemLink::judge($item, $related, $request->enum('state', LinkState::class));

        return back();
    }
}
