<?php

namespace App\Http\Controllers;

use App\Jobs\GenerateDigest;
use App\Models\Digest;
use App\Models\Item;
use App\Models\Project;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Illuminate\View\View;

class DigestController extends Controller
{
    /**
     * Show the latest overview and links to earlier ones.
     */
    public function index(): View
    {
        $digests = Digest::query()->latest('id')->limit(20)->get();

        return view('digests.show', ['digest' => $digests->first(), 'digests' => $digests, ...$this->suggestionData($digests->first())]);
    }

    public function show(Digest $digest): View
    {
        return view('digests.show', [
            'digest' => $digest,
            'digests' => Digest::query()->latest('id')->limit(20)->get(),
            ...$this->suggestionData($digest),
        ]);
    }

    /**
     * The notes and projects the suggested groups refer to, keyed by id and by slug.
     *
     * @return array{suggestedItems: Collection<int, Item>, projectsById: Collection<int, Project>, projectsBySlug: Collection<string, Project>}
     */
    private function suggestionData(?Digest $digest): array
    {
        $suggestions = collect($digest?->project_suggestions ?? []);
        $projects = Project::query()
            ->whereKey($suggestions->pluck('project_id')->filter())
            ->orWhereIn('slug', $suggestions->map(fn (array $group) => Str::slug($group['name'])))
            ->get();

        return [
            'suggestedItems' => Item::query()->whereKey($suggestions->pluck('item_ids')->flatten())->get()->keyBy('id'),
            'projectsById' => $projects->keyBy('id'),
            'projectsBySlug' => $projects->keyBy('slug'),
        ];
    }

    /**
     * Generate a fresh overview in the background.
     */
    public function store(): RedirectResponse
    {
        GenerateDigest::dispatch();

        return to_route('digests.index')->with('status', 'The digest is being generated; refresh the page in a few seconds.');
    }
}
