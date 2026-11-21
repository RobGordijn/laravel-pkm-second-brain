@php
    $hasBody = filled($item->body);
    $showsSummary = filled($item->summary) && (! $hasBody || mb_strlen($item->body) > 600);
    $hasOriginalDump = ! $hasBody || trim($item->raw) !== trim((string) $item->body);

    /*
     * One list of connections: [note, kind label, related-item pivot or null].
     */
    $connections = collect()
        ->concat($item->mentionedItems->map(fn ($note) => [$note, 'Links to', null]))
        ->concat($item->mentionedBy->map(fn ($note) => [$note, 'Mentioned in', null]))
        ->concat($item->relatedItems->map(fn ($note) => [$note, 'Related', $note->pivot]));

    $menuItemClasses = 'flex w-full cursor-pointer items-center rounded-md px-3 py-2 text-left text-sm text-ink-700 hover:bg-ink-50 dark:text-ink-200 dark:hover:bg-ink-800';
@endphp

<x-layout :title="$item->title">
    <a href="{{ route('items.index') }}" class="back-link">← Inbox</a>

    <header class="mt-4 flex items-start gap-4">
        <div class="min-w-0 flex-1">
            <h1 class="page-title text-2xl leading-tight">{{ $item->title ?? Str::limit($item->raw, 80) }}</h1>

            <div class="mt-2 flex flex-wrap items-center gap-1.5 text-xs">
                @if ($item->type)
                    <span class="badge bg-ink-100 text-ink-600 dark:bg-ink-800 dark:text-ink-300">{{ $item->type->label() }}</span>
                @endif
                @if ($item->isAuthored())
                    <span class="badge bg-sage-100 text-sage-700 dark:bg-sage-900 dark:text-sage-200" title="The AI does not change your text">Written by you</span>
                @endif
                @if ($item->isArchived())
                    <span class="badge bg-ink-100 text-ink-500 dark:bg-ink-800 dark:text-ink-400">Archived</span>
                @endif
                @foreach ($item->projects as $project)
                    <a href="{{ route('projects.show', $project) }}" class="chip-project" title="{{ $project->kind->label() }}{{ $project->pivot->isFromAi() ? ' · chosen by AI' : '' }}">▸ {{ $project->name }}</a>
                @endforeach
                @foreach ($item->tags as $tag)
                    <a href="{{ route('items.index', ['tag' => $tag->slug]) }}" class="tag">#{{ $tag->name }}</a>
                @endforeach
                <time class="text-ink-400 dark:text-ink-500" datetime="{{ $item->created_at->toIso8601String() }}">· {{ $item->created_at->diffForHumans() }}</time>
            </div>

            @if ($showsSummary)
                <p class="page-intro mt-3">{{ $item->summary }}</p>
            @endif
        </div>

        <div class="flex shrink-0 items-center gap-1.5">
            <a href="{{ route('items.write.edit', $item) }}" class="btn btn-primary">Edit</a>
            <details class="relative" data-item-menu>
                <summary class="btn btn-secondary list-none px-2.5" aria-label="More actions" title="More actions">
                    <svg class="size-4" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><circle cx="5" cy="12" r="1.8"/><circle cx="12" cy="12" r="1.8"/><circle cx="19" cy="12" r="1.8"/></svg>
                </summary>
                <div class="card absolute right-0 z-20 mt-1 w-72 p-1 shadow-lg">
                    <form action="{{ route('items.retry', $item) }}" method="POST">
                        @csrf
                        <button class="{{ $menuItemClasses }}">Process again</button>
                    </form>
                    @if ($item->isAuthored())
                        <form action="{{ route('items.authored.destroy', $item) }}" method="POST" onsubmit="return confirm('The AI will rewrite this note from your current text and choose its title and type again. Your current text becomes the new original dump. Continue?')">
                            @csrf
                            @method('DELETE')
                            <button class="{{ $menuItemClasses }}">Let the AI rewrite it</button>
                        </form>
                    @endif
                    @if (! $item->key_insight && ! $item->deservesKeyInsight())
                        <form action="{{ route('items.insight.suggest', $item) }}" method="POST">
                            @csrf
                            <button class="{{ $menuItemClasses }}">Add key insight</button>
                        </form>
                    @endif
                    @if ($hasOriginalDump)
                        <a href="#original-dump" class="{{ $menuItemClasses }}" onclick="document.getElementById('original-dump').open = true; this.closest('details').open = false">View original dump</a>
                    @endif
                    <div class="my-1 border-t border-ink-100 dark:border-ink-800"></div>
                    <form action="{{ $item->isArchived() ? route('items.unarchive', $item) : route('items.archive', $item) }}" method="POST">
                        @csrf
                        @if ($item->isArchived()) @method('DELETE') @endif
                        <button class="{{ $menuItemClasses }}">{{ $item->isArchived() ? 'Restore from archive' : 'Archive' }}</button>
                    </form>
                    <form action="{{ route('items.destroy', $item) }}" method="POST" onsubmit="return confirm('Delete this item permanently?')">
                        @csrf
                        @method('DELETE')
                        <button class="{{ $menuItemClasses }} text-red-600! hover:bg-red-50 dark:text-red-400! dark:hover:bg-red-950">Delete</button>
                    </form>
                </div>
            </details>
        </div>
    </header>

    @if ($item->status === \App\Enums\ItemStatus::NeedsFeedback)
        <form action="{{ route('items.clarification', $item) }}" method="POST" class="mt-5 rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm dark:border-amber-900 dark:bg-amber-950/50">
            @csrf
            <label for="clarification-answer" class="block text-amber-900 dark:text-amber-100"><span class="font-semibold">Question from the AI:</span> {{ $item->clarification_question }}</label>
            <div class="mt-2 flex gap-2">
                <input id="clarification-answer" name="answer" required class="field min-w-0 flex-1 border-amber-200 py-1 dark:border-amber-800" placeholder="Short answer…">
                <button class="btn btn-sm btn-warning">Send</button>
            </div>
        </form>
    @elseif ($item->status === \App\Enums\ItemStatus::Failed)
        <div class="mt-5 flex flex-wrap items-center gap-3 rounded-xl border border-red-200 bg-red-50 p-4 text-sm text-red-800 dark:border-red-900 dark:bg-red-950 dark:text-red-200">
            <p class="min-w-0 flex-1">Processing failed: {{ $item->error }}</p>
            <form action="{{ route('items.retry', $item) }}" method="POST">
                @csrf
                <button class="btn btn-sm btn-danger">Try again</button>
            </form>
        </div>
    @elseif ($item->status->isInProgress())
        <p class="mt-5 flex items-center gap-2 rounded-xl border border-cerulean-200 bg-cerulean-50 px-4 py-3 text-sm text-cerulean-700 dark:border-cerulean-800 dark:bg-cerulean-900 dark:text-cerulean-100" data-reload-when-processed="{{ route('items.show', $item) }}">
            <span class="inline-block size-2 animate-pulse rounded-full bg-current"></span>
            The AI is working on this note ({{ Str::lower($item->status->label()) }}). The page refreshes automatically.
        </p>
    @endif

    @include('items._insight')

    @if ($hasBody)
        <x-ai-markdown :text="$item->body" class="card mt-4 p-5 text-[15px] leading-relaxed" />
    @elseif (! $showsSummary)
        <p class="card mt-4 p-5 text-[15px] leading-relaxed whitespace-pre-line">{{ $item->raw }}</p>
    @endif

    @if ($item->actions->isNotEmpty())
        <section class="mt-8">
            <h2 class="eyebrow mb-2">Actions</h2>
            <ul class="card space-y-2 p-4">
                @foreach ($item->actions as $action)
                    @include('items._action', ['action' => $action])
                @endforeach
            </ul>
        </section>
    @endif

    @if ($connections->isNotEmpty())
        <section class="mt-8">
            <h2 class="eyebrow mb-2">Connections</h2>
            <ul class="card-list">
                @foreach ($connections as [$note, $kind, $link])
                    <li class="flex items-center gap-3 px-4 py-2.5 text-sm">
                        <div class="min-w-0 flex-1">
                            <a href="{{ route('items.show', $note) }}" class="block truncate font-medium hover:text-baltic-500 dark:hover:text-baltic-300">{{ $note->title ?? Str::limit($note->raw, 80) }}</a>
                            @if ($link?->reason)
                                <p class="meta">{{ $link->reason }}</p>
                            @endif
                        </div>
                        <span class="badge shrink-0 bg-ink-100 text-ink-500 dark:bg-ink-800 dark:text-ink-400">{{ $kind }}@if ($link) · {{ round($link->similarity * 100) }}%@endif</span>
                        @if ($link?->isSuggested())
                            <form action="{{ route('items.links.update', [$item, $note]) }}" method="POST" class="flex shrink-0 gap-1">
                                @csrf
                                @method('PATCH')
                                <button name="state" value="confirmed" class="rounded px-1.5 text-sage-600 hover:bg-sage-50 dark:text-sage-300 dark:hover:bg-sage-900" title="Correct">✓</button>
                                <button name="state" value="rejected" class="rounded px-1.5 text-ink-400 hover:bg-ink-100 dark:hover:bg-ink-800" title="Not related">✕</button>
                            </form>
                        @elseif ($link)
                            <span class="shrink-0 text-xs text-sage-600 dark:text-sage-300" title="Confirmed">✓</span>
                        @endif
                    </li>
                @endforeach
            </ul>
        </section>
    @endif

    @if ($item->url || $item->why_saved || $hasOriginalDump || $item->clarification_answer)
        <section class="mt-8">
            <h2 class="eyebrow mb-2">Details</h2>
            <dl class="card-list text-sm">
                @if ($item->url)
                    <div class="flex flex-col gap-0.5 px-4 py-2.5 sm:flex-row sm:gap-4">
                        <dt class="meta shrink-0 sm:w-32 sm:pt-0.5">Source</dt>
                        <dd class="min-w-0">
                            <a href="{{ $item->url }}" target="_blank" rel="noopener noreferrer" class="break-all text-baltic-500 hover:underline dark:text-baltic-300">{{ $item->source_title ?? $item->url }}</a>
                            @if ($item->source_fetched_at && blank($item->source_text))
                                <p class="meta">The page could not be read; only the URL was used.</p>
                            @endif
                        </dd>
                    </div>
                @endif
                @if ($item->why_saved)
                    <div class="flex flex-col gap-0.5 px-4 py-2.5 sm:flex-row sm:gap-4">
                        <dt class="meta shrink-0 sm:w-32 sm:pt-0.5">Why saved</dt>
                        <dd>{{ $item->why_saved }}</dd>
                    </div>
                @endif
                @if ($item->clarification_answer)
                    <div class="flex flex-col gap-0.5 px-4 py-2.5 sm:flex-row sm:gap-4">
                        <dt class="meta shrink-0 sm:w-32 sm:pt-0.5">Your clarification</dt>
                        <dd>{{ $item->clarification_answer }}</dd>
                    </div>
                @endif
                @if ($hasOriginalDump)
                    <details id="original-dump" class="group px-4 py-2.5">
                        <summary class="flex cursor-pointer list-none items-center gap-4">
                            <span class="meta shrink-0 sm:w-32">Original dump</span>
                            <span class="link-subtle group-open:hidden">show</span>
                        </summary>
                        <pre class="mt-2 font-sans text-sm whitespace-pre-wrap text-ink-600 dark:text-ink-400">{{ $item->raw }}</pre>
                    </details>
                @endif
            </dl>
        </section>
    @endif
</x-layout>
