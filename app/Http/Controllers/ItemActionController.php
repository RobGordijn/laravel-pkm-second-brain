<?php

namespace App\Http\Controllers;

use App\Models\ItemAction;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ItemActionController extends Controller
{
    /**
     * Toggle an action between open and done.
     */
    public function update(Request $request, ItemAction $action): JsonResponse|RedirectResponse
    {
        $action->update(['done_at' => $action->isDone() ? null : now()]);

        if ($request->expectsJson()) {
            return response()->json(['done' => $action->isDone()]);
        }

        return back();
    }
}
