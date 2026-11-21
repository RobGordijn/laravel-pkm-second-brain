<x-layout title="Review">
    <div class="mb-6">
        <h1 class="page-title">Review</h1>
        <p class="page-intro">
            Your weekly round: give every new note a place. Which project does this help? Otherwise, which area?
            Otherwise it is reference, or it can go to the archive.
            @if ($remaining > $items->count())
                You are seeing the oldest {{ $items->count() }} of {{ $remaining }}.
            @endif
        </p>
    </div>

    @if ($items->isEmpty())
        <p class="empty-state">Everything has a place. See you next week.</p>
    @else
        <ul class="space-y-3">
            @foreach ($items as $item)
                <li class="card p-4 text-sm" data-review-item="{{ $item->id }}">
                    <div class="flex items-start gap-3">
                        <a href="{{ route('items.show', $item) }}" class="group min-w-0 flex-1">
                            <h2 class="font-semibold leading-snug text-ink-900 group-hover:text-baltic-500 dark:text-white dark:group-hover:text-baltic-300">{{ $item->title ?? Str::limit($item->raw, 80) }}</h2>
                            @if ($item->summary)
                                <p class="mt-1 text-ink-600 dark:text-ink-400">{{ $item->summary }}</p>
                            @endif
                            @if ($item->why_saved)
                                <p class="meta mt-1">Why saved: {{ $item->why_saved }}</p>
                            @endif
                        </a>
                        <span class="shrink-0 text-xs text-ink-400 dark:text-ink-500">{{ $item->created_at->diffForHumans() }}</span>
                    </div>

                    @if ($item->tags->isNotEmpty())
                        <div class="mt-2 flex flex-wrap gap-1.5">
                            @foreach ($item->tags as $tag)
                                <span class="tag">#{{ $tag->name }}</span>
                            @endforeach
                        </div>
                    @endif

                    @php($hasNameError = $errors->has('name') && (int) old('review_item') === $item->id)

                    <div class="mt-4 space-y-2 border-t border-ink-100 pt-3 dark:border-ink-800">
                        @if ($projects->isNotEmpty())
                            <form action="{{ route('review.store', $item) }}" method="POST" class="space-y-2">
                                @csrf
                                <input type="hidden" name="decision" value="project">
                                @foreach ($projects->groupBy(fn ($project) => $project->kind->pluralLabel()) as $group => $groupProjects)
                                    <div class="flex flex-wrap items-center gap-1.5">
                                        <span class="eyebrow w-20 shrink-0 text-[11px]">{{ $group }}</span>
                                        @foreach ($groupProjects as $project)
                                            @php($isAiChoice = $item->projects->firstWhere('id', $project->id)?->pivot->isFromAi())
                                            <button name="project_id" value="{{ $project->id }}" @class([
                                                'btn btn-sm rounded-full px-2.5',
                                                'btn-primary' => $isAiChoice,
                                                'border border-cerulean-200 bg-cerulean-50 text-cerulean-700 hover:border-cerulean-400 hover:bg-cerulean-100 dark:border-cerulean-700 dark:bg-cerulean-900 dark:text-cerulean-100 dark:hover:bg-cerulean-800' => ! $isAiChoice,
                                            ]) @if ($isAiChoice) title="Suggested by AI" @endif>{{ $project->name }}@if ($isAiChoice) <span class="opacity-75">· AI suggestion</span>@endif</button>
                                        @endforeach
                                    </div>
                                @endforeach
                            </form>
                        @endif

                        <div class="flex flex-wrap items-start gap-1.5">
                            <span class="eyebrow w-20 shrink-0 pt-1.5 text-[11px]">New</span>
                            <details class="group min-w-0 flex-1" @if ($hasNameError) open @endif>
                                <summary class="btn btn-sm list-none rounded-full border border-dashed border-ink-300 px-2.5 text-ink-600 hover:border-baltic-400 hover:text-baltic-500 group-open:hidden dark:border-ink-600 dark:text-ink-300">+ Project or area</summary>
                                <form action="{{ route('review.store', $item) }}" method="POST" class="flex flex-wrap items-center gap-1.5">
                                    @csrf
                                    <input type="hidden" name="decision" value="new_project">
                                    <input type="hidden" name="review_item" value="{{ $item->id }}">
                                    <input name="name" value="{{ $hasNameError ? old('name') : '' }}" required maxlength="100" placeholder="Name, e.g. Renovate the bathroom" aria-label="Name of the new project or area" class="field field-sm min-w-0 flex-1 basis-48 py-1.5 text-sm">
                                    <button name="kind" value="project" class="btn btn-sm btn-primary">As project</button>
                                    <button name="kind" value="area" class="btn btn-sm btn-secondary">As area</button>
                                </form>
                                @if ($hasNameError)
                                    <span class="field-error">{{ $errors->first('name') }}</span>
                                @endif
                            </details>
                        </div>

                        <div class="flex flex-wrap items-center gap-1.5">
                            <span class="eyebrow w-20 shrink-0 text-[11px]">Otherwise</span>
                            @foreach (['resource' => 'Reference', 'archive' => 'Archive'] as $decision => $label)
                                <form action="{{ route('review.store', $item) }}" method="POST">
                                    @csrf
                                    <input type="hidden" name="decision" value="{{ $decision }}">
                                    <button class="btn btn-sm btn-secondary rounded-full px-2.5" title="{{ $decision === 'resource' ? 'No project; findable through tags and search' : 'Out of sight, not deleted' }}">{{ $label }}</button>
                                </form>
                            @endforeach
                        </div>
                    </div>
                </li>
            @endforeach
        </ul>
    @endif

</x-layout>
