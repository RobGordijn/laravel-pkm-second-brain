<?php

namespace App\Http\Controllers;

use App\Enums\AssignmentSource;
use App\Enums\ProjectKind;
use App\Http\Requests\StoreDigestProjectRequest;
use App\Models\Digest;
use App\Models\Item;
use App\Models\Project;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class DigestProjectController extends Controller
{
    /**
     * Turn a group the overview suggested into a project or area, or add the notes to an existing one
     * with the same name. The suggestion remembers the project, so the button becomes a link.
     */
    public function store(StoreDigestProjectRequest $request, Digest $digest, int $suggestion): RedirectResponse
    {
        $suggestions = $digest->project_suggestions ?? [];
        abort_unless(isset($suggestions[$suggestion]), 404);
        $group = $suggestions[$suggestion];

        $project = DB::transaction(function () use ($request, $digest, $suggestions, $suggestion, $group) {
            $name = Str::squish($request->validated('name'));

            $project = Project::query()->where('slug', Str::slug($name))->first()
                ?? Project::create([
                    'name' => $name,
                    'kind' => ProjectKind::tryFrom($group['kind']) ?? ProjectKind::Project,
                    'goal' => $group['goal'] ?: null,
                ]);

            $itemIds = Item::query()->whereKey($group['item_ids'])->pluck('id');
            $project->items()->syncWithoutDetaching($itemIds->mapWithKeys(fn (int $id) => [$id => ['source' => AssignmentSource::User]])->all());
            Item::query()->whereKey($itemIds)->whereNull('reviewed_at')->update(['reviewed_at' => now()]);

            $suggestions[$suggestion]['project_id'] = $project->id;
            $digest->update(['project_suggestions' => $suggestions]);

            return $project;
        });

        return to_route('projects.show', $project)
            ->with('status', $project->wasRecentlyCreated ? "{$project->kind->label()} created from the digest." : 'Notes added.');
    }
}
