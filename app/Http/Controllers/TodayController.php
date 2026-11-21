<?php

namespace App\Http\Controllers;

use App\Models\ItemAction;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\View\View;

class TodayController extends Controller
{
    /**
     * Open actions: overdue and due today first, then upcoming, then without a date.
     * Archived notes are left out; within each group, actions from active projects come first.
     */
    public function __invoke(): View
    {
        $actions = ItemAction::query()
            ->with(['item.projects' => fn (BelongsToMany $projects) => $projects->active()])
            ->open()
            ->whereHas('item', fn (Builder $items) => $items->unarchived())
            ->orderByRaw('due_on is null')
            ->orderBy('due_on')
            ->latest('id')
            ->get()
            ->sortBy(fn (ItemAction $action) => $action->item->projects->isEmpty());

        $today = today();

        return view('today', [
            'dueNow' => $actions->filter(fn (ItemAction $action) => $action->due_on?->lte($today)),
            'upcoming' => $actions->filter(fn (ItemAction $action) => $action->due_on?->gt($today)),
            'someday' => $actions->whereNull('due_on'),
        ]);
    }
}
