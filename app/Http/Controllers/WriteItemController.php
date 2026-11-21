<?php

namespace App\Http\Controllers;

use App\Enums\ItemType;
use App\Http\Requests\WriteItemRequest;
use App\Models\Item;
use App\Models\Project;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

/**
 * Writing a note yourself: the text stays yours, the AI only adds tags, summary and links.
 */
class WriteItemController extends Controller
{
    public function create(): View
    {
        return view('items.write', ['item' => null]);
    }

    public function store(WriteItemRequest $request): RedirectResponse
    {
        $item = Item::write($request->validated('body'), $request->validated('title'));

        return to_route('items.write.edit', $item)->with('status', 'Saved. The AI adds tags and links; your text stays as it is.');
    }

    /**
     * The text and title in the editor; type, tags, projects and the rest in the properties panel beside it.
     */
    public function edit(Item $item): View
    {
        $item->load(['tags', 'projects']);

        return view('items.write', [
            'item' => $item,
            'types' => ItemType::cases(),
            'projects' => Project::query()
                ->where(fn (Builder $query) => $query->active()->orWhereKey($item->projects->modelKeys()))
                ->orderByDesc('kind')
                ->orderBy('name')
                ->get(),
        ]);
    }

    /**
     * Autosave (JSON) or the final save that returns to the note.
     */
    public function update(WriteItemRequest $request, Item $item): JsonResponse|RedirectResponse
    {
        $item->saveWriting($request->validated('body'), $request->validated('title'));

        if ($request->expectsJson()) {
            return response()->json(['saved_at' => now()->format('H:i')]);
        }

        return to_route('items.show', $item)->with('status', 'Saved.');
    }
}
