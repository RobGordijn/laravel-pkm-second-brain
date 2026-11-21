@php
    $statusClasses = match ($item->status) {
        \App\Enums\ItemStatus::NeedsFeedback => 'bg-amber-100 text-amber-800 dark:bg-amber-900 dark:text-amber-200',
        \App\Enums\ItemStatus::Failed => 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200',
        default => 'bg-baltic-50 text-baltic-600 dark:bg-baltic-800 dark:text-baltic-100',
    };
@endphp

<article
    data-item-card="{{ $item->id }}"
    @if ($item->status->isInProgress()) data-in-progress @endif
    class="card p-4 transition-colors hover:border-ink-300 dark:hover:border-ink-700"
>
    <div class="flex items-start justify-between gap-3">
        <a href="{{ route('items.show', $item) }}" class="group min-w-0 flex-1">
            @if ($item->title)
                <h2 class="font-semibold leading-snug text-ink-900 group-hover:text-baltic-500 dark:text-white dark:group-hover:text-baltic-300">{{ $item->title }}</h2>
                @if ($item->key_insight && ! request()->routeIs('items.show'))
                    <p class="mt-1.5 border-l-2 border-lime-500 pl-2 text-sm text-ink-800 dark:border-lime-400 dark:text-ink-100">{{ $item->key_insight }}</p>
                @endif
                <p class="mt-1 text-sm text-ink-600 dark:text-ink-400">{{ $item->summary }}</p>
            @else
                <p class="line-clamp-3 text-sm whitespace-pre-line text-ink-600 dark:text-ink-400">{{ $item->raw }}</p>
            @endif
        </a>
        <div class="flex shrink-0 items-center gap-1.5 text-xs">
            @if ($item->isArchived())
                <span class="badge bg-ink-100 text-ink-500 dark:bg-ink-800 dark:text-ink-400">Archived</span>
            @endif
            @if ($item->type)
                <span class="meta">{{ $item->type->label() }}</span>
            @endif
            @if ($item->status !== \App\Enums\ItemStatus::Ready)
                <span class="badge {{ $statusClasses }}">
                    @if ($item->status->isInProgress())
                        <span class="inline-block size-1.5 animate-pulse rounded-full bg-current align-middle"></span>
                    @endif
                    {{ $item->status->label() }}
                </span>
            @endif
        </div>
    </div>

    @if ($item->status === \App\Enums\ItemStatus::NeedsFeedback)
        <form action="{{ route('items.clarification', $item) }}" method="POST" class="mt-3 rounded-lg border border-amber-200 bg-amber-50 p-3 dark:border-amber-900 dark:bg-amber-950/50">
            @csrf
            <label class="block text-sm text-amber-900 dark:text-amber-100">{{ $item->clarification_question }}</label>
            <div class="mt-2 flex gap-2">
                <input name="answer" required class="field min-w-0 flex-1 border-amber-200 py-1 dark:border-amber-800" placeholder="Short answer…">
                <button class="btn btn-sm btn-warning">Send</button>
            </div>
        </form>
    @endif

    @if ($item->actions->isNotEmpty())
        <ul class="mt-3 space-y-1">
            @foreach ($item->actions as $action)
                @include('items._action', ['action' => $action])
            @endforeach
        </ul>
    @endif

    <div class="mt-3 flex flex-wrap items-center gap-1.5 text-xs text-ink-500 dark:text-ink-400">
        @foreach ($item->projects as $project)
            <a href="{{ route('projects.show', $project) }}" class="chip-project" title="{{ $project->kind->label() }}{{ $project->pivot->isFromAi() ? ' · chosen by AI' : '' }}">▸ {{ $project->name }}</a>
        @endforeach
        @foreach ($item->tags as $tag)
            <a href="{{ route('items.index', ['tag' => $tag->slug]) }}" class="tag">#{{ $tag->name }}</a>
        @endforeach
        <time class="ml-auto pl-2 text-ink-400 dark:text-ink-500" datetime="{{ $item->created_at->toIso8601String() }}">{{ $item->created_at->diffForHumans() }}</time>
    </div>
</article>
