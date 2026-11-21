<?php

namespace App\Http\Controllers;

use App\Enums\ProjectKind;
use App\Http\Requests\ProjectRequest;
use App\Models\Project;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class ProjectController extends Controller
{
    /**
     * Active projects and areas with their open work, plus the archive.
     * Counts match the project page: an active project leaves out notes archived on their own,
     * an archived project counts the notes that went into the archive with it.
     */
    public function index(): View
    {
        $visibleItems = fn (Builder $items) => $items->where(fn (Builder $items) => $items
            ->whereNull('items.archived_at')
            ->orWhereNotNull('projects.archived_at'));

        $projects = Project::query()
            ->withCount([
                'items' => $visibleItems,
                'items as open_actions_count' => fn (Builder $items) => $visibleItems($items)
                    ->join('item_actions', 'item_actions.item_id', '=', 'items.id')
                    ->whereNull('item_actions.done_at'),
            ])
            ->orderByRaw('due_on is null')
            ->orderBy('due_on')
            ->orderBy('name')
            ->get();

        $active = $projects->whereNull('archived_at');

        return view('projects.index', [
            'projects' => $active->where('kind', ProjectKind::Project),
            'areas' => $active->where('kind', ProjectKind::Area),
            'archived' => $projects->whereNotNull('archived_at')->sortByDesc('archived_at'),
            'kinds' => ProjectKind::cases(),
        ]);
    }

    public function store(ProjectRequest $request): RedirectResponse
    {
        $project = Project::create($request->validated());

        return to_route('projects.show', $project)->with('status', "{$project->kind->label()} created.");
    }

    /**
     * The notes and open actions that belong to one project or area.
     */
    public function show(Project $project): View
    {
        $items = $project->items()
            ->with(['tags', 'actions', 'projects'])
            ->when(! $project->isArchived(), fn (Builder $items) => $items->whereNull('items.archived_at'))
            ->latest()
            ->get();

        return view('projects.show', [
            'project' => $project,
            'items' => $items,
            'openActions' => $items->flatMap->actions->reject->isDone()->sortBy(fn ($action) => $action->due_on ?? '9999-12-31')->values(),
            'kinds' => ProjectKind::cases(),
        ]);
    }

    public function update(ProjectRequest $request, Project $project): RedirectResponse
    {
        $project->update($request->validated());

        return to_route('projects.show', $project)->with('status', 'Saved.');
    }
}
