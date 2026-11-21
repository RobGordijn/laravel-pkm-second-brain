<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreItemRequest;
use App\Models\Item;
use Illuminate\Http\JsonResponse;

class CaptureController extends Controller
{
    /**
     * Capture a dump from an external client such as an iOS Shortcut.
     */
    public function __invoke(StoreItemRequest $request): JsonResponse
    {
        $item = Item::capture($request->validated('raw'), $request->validated('why_saved'));

        return response()->json([
            'id' => $item->id,
            'status' => $item->status->value,
            'url' => route('items.show', $item),
        ], 201);
    }
}
