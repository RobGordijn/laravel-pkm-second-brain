<x-layout title="Digest">
    <div class="mb-6 flex items-center justify-between gap-4">
        <h1 class="page-title">Digest</h1>
        <form action="{{ route('digests.store') }}" method="POST">
            @csrf
            <button class="btn btn-primary">Generate new digest</button>
        </form>
    </div>

    @if ($digest)
        <p class="mb-3 text-xs text-ink-500">
            {{ $digest->period_start->translatedFormat('j M') }} – {{ $digest->period_end->translatedFormat('j M Y') }}
            · generated {{ $digest->created_at->diffForHumans() }}
        </p>

        <dl class="mb-4 grid grid-cols-2 gap-2 text-center sm:grid-cols-4">
            @foreach ([
                'new_items' => 'Newly captured',
                'open_actions' => 'Open actions',
                'overdue_actions' => 'Overdue',
                'needs_feedback' => 'Open questions',
            ] as $key => $label)
                <div class="stat">
                    <dd @class(['text-xl font-semibold tabular-nums', 'text-red-600' => $key === 'overdue_actions' && $digest->stats[$key] > 0])>{{ $digest->stats[$key] ?? 0 }}</dd>
                    <dt class="meta">{{ $label }}</dt>
                </div>
            @endforeach
        </dl>

        <x-ai-markdown :text="$digest->content" class="card p-4 text-sm" />

        @if (filled($digest->project_suggestions))
            <section class="mt-6">
                <h2 class="eyebrow mb-2">These belong together</h2>
                <div class="space-y-3">
                    @foreach ($digest->project_suggestions as $index => $group)
                        @php
                            $createdProject = isset($group['project_id']) ? $projectsById->get($group['project_id']) : null;
                            $existingProject = $projectsBySlug->get(Str::slug($group['name']));
                        @endphp
                        <div class="rounded-xl border border-cerulean-200 bg-cerulean-50 p-4 text-sm dark:border-cerulean-800 dark:bg-cerulean-900">
                            @if ($group['goal'])
                                <p class="text-ink-700 dark:text-ink-300">{{ $group['goal'] }}</p>
                            @endif
                            <ul class="mt-2 space-y-0.5">
                                @foreach ($group['item_ids'] as $itemId)
                                    @if ($suggestedItems->has($itemId))
                                        <li><a href="{{ route('items.show', $itemId) }}" class="text-ink-600 hover:underline dark:text-ink-400">{{ $suggestedItems[$itemId]->title ?? Str::limit($suggestedItems[$itemId]->raw, 70) }}</a></li>
                                    @endif
                                @endforeach
                            </ul>
                            @if ($createdProject)
                                <p class="mt-3 text-xs text-sage-700 dark:text-sage-400">
                                    ✓ Filed under <a href="{{ route('projects.show', $createdProject) }}" class="font-medium underline">{{ $createdProject->name }}</a>
                                </p>
                            @else
                                <form action="{{ route('digests.projects.store', [$digest, $index]) }}" method="POST" class="mt-3 flex flex-wrap items-center gap-2">
                                    @csrf
                                    <input name="name" value="{{ $group['name'] }}" required maxlength="100" aria-label="Name" class="field min-w-0 flex-1 py-1">
                                    <button class="btn btn-sm btn-primary">
                                        @if ($existingProject)
                                            Add to {{ Str::lower($existingProject->kind->label()) }}
                                        @else
                                            Create {{ $group['kind'] === 'area' ? 'area' : 'project' }}
                                        @endif
                                    </button>
                                    @error('name', 'suggestion'.$index) <p class="field-error w-full">{{ $message }}</p> @enderror
                                </form>
                            @endif
                        </div>
                    @endforeach
                </div>
            </section>
        @endif

        @if ($digests->count() > 1)
            <section class="mt-8">
                <h2 class="eyebrow mb-2">Earlier digests</h2>
                <ul class="space-y-1 text-sm">
                    @foreach ($digests as $earlier)
                        <li>
                            <a href="{{ route('digests.show', $earlier) }}" @class(['hover:underline', 'font-medium' => $earlier->is($digest)])>
                                {{ $earlier->period_start->translatedFormat('j M') }} – {{ $earlier->period_end->translatedFormat('j M Y') }}
                            </a>
                        </li>
                    @endforeach
                </ul>
            </section>
        @endif
    @else
        <p class="empty-state">
            No digest yet. One is generated automatically every Monday at 08:00, or generate one now.
        </p>
    @endif
</x-layout>
