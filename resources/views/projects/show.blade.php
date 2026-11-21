<x-layout :title="$project->name">
    <a href="{{ route('projects.index') }}" class="back-link">← Projects</a>

    <div class="mt-4 mb-6 flex items-start gap-4">
        <div class="min-w-0 flex-1">
            <p class="eyebrow">
                {{ $project->kind->label() }}
                @if ($project->due_on) · deadline {{ $project->due_on->translatedFormat('j F Y') }} @endif
                @if ($project->isArchived()) · <span class="text-amber-700 dark:text-amber-400">archived</span> @endif
            </p>
            <h1 class="page-title mt-1">{{ $project->name }}</h1>
            @if ($project->goal)
                <p class="page-intro">{{ $project->goal }}</p>
            @endif
        </div>
        @if ($project->isArchived())
            <form action="{{ route('projects.unarchive', $project) }}" method="POST">
                @csrf
                @method('DELETE')
                <button class="btn btn-secondary">Reactivate</button>
            </form>
        @else
            <form action="{{ route('projects.archive', $project) }}" method="POST" onsubmit="return confirm('Archive this {{ Str::lower($project->kind->label()) }}? Notes that belong nowhere else are archived with it.')">
                @csrf
                <button class="btn btn-secondary">{{ $project->kind === \App\Enums\ProjectKind::Project ? 'Complete' : 'Archive' }}</button>
            </form>
        @endif
    </div>

    @if ($openActions->isNotEmpty())
        <section class="mb-8">
            <h2 class="eyebrow mb-2">Open actions</h2>
            <ul class="card space-y-2 p-4">
                @foreach ($openActions as $action)
                    @include('items._action', ['action' => $action])
                @endforeach
            </ul>
        </section>
    @endif

    <section class="mb-8">
        <h2 class="eyebrow mb-2">Notes</h2>
        <div class="space-y-3">
            @forelse ($items as $item)
                @include('items._card', ['item' => $item])
            @empty
                <p class="py-8 text-center text-sm text-ink-500">No notes yet. New dumps that fit here are filed automatically, or choose this {{ Str::lower($project->kind->label()) }} on a note under “Edit”.</p>
            @endforelse
        </div>
    </section>

    <details class="disclosure" @if ($errors->any()) open @endif>
        <summary>Edit</summary>
        <form action="{{ route('projects.update', $project) }}" method="POST" class="mt-4 text-sm">
            @csrf
            @method('PATCH')
            @include('projects._form', ['project' => $project])
            <button class="btn btn-primary mt-3">Save</button>
        </form>
    </details>
</x-layout>
