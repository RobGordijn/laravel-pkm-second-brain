<x-layout title="Tags">
    <div class="mb-6 flex items-center justify-between gap-4">
        <h1 class="page-title">Tags</h1>
        <form action="{{ route('tags.suggestions') }}" method="POST">
            @csrf
            <button class="btn btn-primary">Find duplicate tags</button>
        </form>
    </div>

    @if ($suggestions->isNotEmpty())
        <section class="mb-8 space-y-2">
            <h2 class="eyebrow">Suggestions</h2>
            @foreach ($suggestions as $suggestion)
                <form action="{{ route('tags.merge') }}" method="POST" class="flex items-center gap-3 rounded-lg border border-amber-200 bg-amber-50 px-3 py-2 text-sm dark:border-amber-900 dark:bg-amber-950/50">
                    @csrf
                    <input type="hidden" name="target_id" value="{{ $suggestion['target']->id }}">
                    @foreach ($suggestion['sources'] as $source)
                        <input type="hidden" name="source_ids[]" value="{{ $source->id }}">
                    @endforeach
                    <div class="min-w-0 flex-1">
                        <p>
                            {{ $suggestion['sources']->map(fn ($tag) => '#'.$tag->name)->implode(', ') }}
                            → <strong>#{{ $suggestion['target']->name }}</strong>
                        </p>
                        <p class="meta">{{ $suggestion['reason'] }}</p>
                    </div>
                    <button class="btn btn-sm btn-warning">Merge</button>
                </form>
            @endforeach
        </section>
    @endif

    @if ($tags->isEmpty())
        <p class="empty-state">No tags yet.</p>
    @else
        <ul class="card-list text-sm">
            @foreach ($tags as $tag)
                <li class="flex items-center gap-3 px-4 py-2.5">
                    <span class="flex-1"><a href="{{ route('items.index', ['tag' => $tag->slug]) }}" class="tag">#{{ $tag->name }}</a></span>
                    <span class="text-xs text-ink-400 tabular-nums">{{ $tag->items_count }}</span>
                    <details class="relative">
                        <summary class="link-subtle list-none">merge…</summary>
                        <form action="{{ route('tags.merge') }}" method="POST" class="card absolute right-0 z-10 mt-1 flex w-64 gap-2 p-2 shadow-lg">
                            @csrf
                            <input type="hidden" name="source_ids[]" value="{{ $tag->id }}">
                            <select name="target_id" class="field field-sm min-w-0 flex-1">
                                @foreach ($tags->where('id', '!=', $tag->id) as $target)
                                    <option value="{{ $target->id }}">into #{{ $target->name }}</option>
                                @endforeach
                            </select>
                            <button class="btn btn-sm btn-primary">OK</button>
                        </form>
                    </details>
                </li>
            @endforeach
        </ul>
    @endif
</x-layout>
