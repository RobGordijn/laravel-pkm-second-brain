<?php

namespace App\Http\Controllers;

use App\Models\Item;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class ItemLookupController extends Controller
{
    /**
     * Notes whose title matches what the author is typing after [[, for linking by hand.
     */
    public function __invoke(Request $request): JsonResponse
    {
        $terms = Str::squish((string) $request->query('q'));

        $items = Item::query()
            ->unarchived()
            ->whereNotNull('title')
            ->when($terms !== '', fn (Builder $query) => $query->where('title', 'ilike', '%'.addcslashes($terms, '%_\\').'%'))
            ->when($request->integer('except'), fn (Builder $query, int $id) => $query->whereKeyNot($id))
            ->latest('updated_at')
            ->limit(8)
            ->get(['id', 'title']);

        return response()->json($items->map(fn (Item $item) => ['id' => $item->id, 'title' => $item->title]));
    }
}
