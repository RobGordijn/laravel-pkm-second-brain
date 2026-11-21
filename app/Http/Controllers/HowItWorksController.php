<?php

namespace App\Http\Controllers;

use App\Models\Item;
use App\Models\ItemAction;
use App\Models\Project;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\View\View;

class HowItWorksController extends Controller
{
    /**
     * A visual explanation of the flow from dump to use, with the live numbers at each step.
     */
    public function __invoke(): View
    {
        return view('how-it-works', [
            'counts' => [
                'items' => Item::query()->unarchived()->count(),
                'needsFeedback' => Item::query()->needsFeedback()->count(),
                'awaitingReview' => Item::query()->awaitingReview()->count(),
                'projects' => Project::query()->active()->count(),
                'archived' => Item::query()->whereNotNull('archived_at')->count(),
                'toDistill' => Item::query()->unarchived()->awaitingDistillation()->count(),
                'openActions' => ItemAction::query()->open()->whereHas('item', fn (Builder $items) => $items->unarchived())->count(),
            ],
        ]);
    }
}
