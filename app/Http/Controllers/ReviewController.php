<?php

namespace App\Http\Controllers;

use App\Enums\AssignmentSource;
use App\Http\Requests\ReviewItemRequest;
use App\Models\Item;
use App\Models\Project;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\View\View;

class ReviewController extends Controller
{
    /**
     * Notes in one batch of the weekly review.
     */
    private const BATCH_SIZE = 20;

    /**
     * The weekly review: new notes the author has not placed yet, oldest first, one batch at a time.
     */
    public function index(): View
    {
        $query = Item::query()->awaitingReview();

        return view('review.index', [
            'items' => (clone $query)->with(['tags', 'projects'])->oldest()->limit(self::BATCH_SIZE)->get(),
            'remaining' => $query->count(),
            'projects' => Project::query()->active()->orderByDesc('kind')->orderBy('name')->get(),
        ]);
    }

    /**
     * Place one note: in a project or area (an existing one or one created on the spot),
     * as reference material (tags only), or in the archive. The AI's project choice is replaced by the author's decision.
     */
    public function store(ReviewItemRequest $request, Item $item): RedirectResponse
    {
        $decision = $request->validated('decision');

        DB::transaction(function () use ($item, $decision, $request) {
            $item->projects()->wherePivot('source', AssignmentSource::Ai)->detach();

            $projectId = match ($decision) {
                'project' => (int) $request->validated('project_id'),
                'new_project' => $this->findOrCreateProject($request->validated('name'), $request->validated('kind'))->id,
                default => null,
            };

            if ($projectId !== null) {
                $item->projects()->syncWithoutDetaching([$projectId => ['source' => AssignmentSource::User]]);
            }

            $item->update([
                'reviewed_at' => now(),
                'archived_at' => $decision === 'archive' ? now() : $item->archived_at,
            ]);
        });

        return to_route('review.index')->with('status', match ($decision) {
            'project', 'new_project' => 'Filed.',
            'resource' => 'Kept as reference.',
            'archive' => 'Archived.',
        });
    }

    /**
     * Reuse an active project with the same name, so a typo-free duplicate never splits the notes.
     */
    private function findOrCreateProject(string $name, string $kind): Project
    {
        return Project::query()->active()->firstWhere('slug', Str::slug(Str::squish($name)))
            ?? Project::create(['name' => $name, 'kind' => $kind]);
    }
}
