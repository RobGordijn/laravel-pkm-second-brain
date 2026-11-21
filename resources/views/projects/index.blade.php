<x-layout title="Projects">
    <div class="mb-6">
        <h1 class="page-title">Projects & areas</h1>
        <p class="page-intro">Notes are filed under the project they contribute to, otherwise under an area. Whatever fits nowhere stays as reference under its tags.</p>
    </div>

    <div class="mb-8 grid items-start gap-3 sm:grid-cols-2">
        @foreach ($kinds as $kind)
            <details class="disclosure" @if ($errors->any() && old('kind') === $kind->value) open @endif>
                <summary>New {{ Str::lower($kind->label()) }}</summary>
                <form action="{{ route('projects.store') }}" method="POST" class="mt-4 text-sm">
                    @csrf
                    @include('projects._form', ['project' => null, 'fixedKind' => $kind])
                    <button class="btn btn-primary mt-3">Create {{ Str::lower($kind->label()) }}</button>
                </form>
            </details>
        @endforeach
    </div>

    @foreach (['Projects' => $projects, 'Areas' => $areas] as $heading => $group)
        <section class="mb-8">
            <h2 class="eyebrow mb-2">{{ $heading }}</h2>
            @if ($group->isEmpty())
                <p class="empty-state py-4 text-left">
                    {{ $heading === 'Projects' ? 'No projects yet: something with a goal and an end, such as “Renovate the bathroom”.' : 'No areas yet: something you keep up with continuously, such as “Health” or “Finances”.' }}
                </p>
            @else
                <ul class="card-list text-sm">
                    @foreach ($group as $project)
                        <li class="group relative flex items-center gap-3 px-4 py-3 transition-colors hover:bg-ink-50 dark:hover:bg-ink-800/50">
                            <span @class(['size-2 shrink-0 rounded-full', 'bg-baltic-400' => $heading === 'Projects', 'bg-sage-400' => $heading !== 'Projects'])></span>
                            <div class="min-w-0 flex-1">
                                <a href="{{ route('projects.show', $project) }}" class="font-medium text-ink-900 after:absolute after:inset-0 group-hover:text-baltic-500 dark:text-white dark:group-hover:text-baltic-300">{{ $project->name }}</a>
                                @if ($project->goal)
                                    <p class="meta truncate">{{ $project->goal }}</p>
                                @endif
                            </div>
                            @if ($project->open_actions_count > 0)
                                <span class="badge bg-baltic-50 text-baltic-600 dark:bg-baltic-800 dark:text-baltic-100">{{ $project->open_actions_count }} open</span>
                            @endif
                            @if ($project->due_on)
                                <span @class(['badge', 'bg-red-50 text-red-700 dark:bg-red-950 dark:text-red-300' => $project->due_on->isPast() && ! $project->due_on->isToday(), 'bg-ink-100 text-ink-600 dark:bg-ink-800 dark:text-ink-300' => ! $project->due_on->isPast() || $project->due_on->isToday()])>{{ $project->due_on->translatedFormat('j M') }}</span>
                            @endif
                            <span class="w-6 text-right text-xs text-ink-400 tabular-nums dark:text-ink-500" title="Notes">{{ $project->items_count }}</span>
                        </li>
                    @endforeach
                </ul>
            @endif
        </section>
    @endforeach


    @if ($archived->isNotEmpty())
        <details>
            <summary class="eyebrow cursor-pointer hover:text-ink-700 dark:hover:text-ink-200">Archive ({{ $archived->count() }})</summary>
            <ul class="card-list mt-2 text-sm">
                @foreach ($archived as $project)
                    <li class="flex items-center gap-3 px-4 py-2.5 text-ink-500 dark:text-ink-400">
                        <a href="{{ route('projects.show', $project) }}" class="flex-1 hover:underline">{{ $project->name }}</a>
                        <span class="meta">{{ $project->kind->label() }} · {{ $project->archived_at->translatedFormat('j M Y') }}</span>
                    </li>
                @endforeach
            </ul>
        </details>
    @endif
</x-layout>
