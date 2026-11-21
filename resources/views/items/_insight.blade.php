@php
    $draft = old('key_insight');
    $isInvited = $item->deservesKeyInsight() || $draft !== null || $errors->has('key_insight');
@endphp

@if ($item->key_insight && $draft === null)
    <details class="group mt-4 rounded-xl border border-lime-300 border-l-4 border-l-lime-500 bg-lime-50 px-4 py-3 text-sm dark:border-lime-800 dark:border-l-lime-400 dark:bg-lime-950">
        <summary class="flex cursor-pointer list-none items-start gap-3">
            <div class="min-w-0 flex-1">
                <p class="text-xs font-semibold tracking-wider text-lime-700 uppercase dark:text-lime-300">Key insight</p>
                <p class="mt-0.5 font-medium">{{ $item->key_insight }}</p>
            </div>
            <span class="link-subtle group-open:hidden">edit</span>
        </summary>
        <form action="{{ route('items.insight.update', $item) }}" method="POST" class="mt-3 flex gap-2">
            @csrf
            @method('PATCH')
            <input name="key_insight" value="{{ $item->key_insight }}" maxlength="500" aria-label="Key insight" class="field min-w-0 flex-1 py-1">
            <button class="btn btn-sm btn-primary">Save</button>
        </form>
    </details>
@elseif ($isInvited)
    <section class="mt-4 rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm dark:border-amber-900 dark:bg-amber-950/40" data-insight-invite>
        <p class="text-amber-900 dark:text-amber-100">
            @if ($draft !== null)
                Suggested by the AI. Make it your own and save:
            @else
                You have used this note {{ $item->use_count }}×. What is the key insight, in your own words?
            @endif
        </p>
        <form action="{{ route('items.insight.update', $item) }}" method="POST" class="mt-2 flex flex-wrap gap-2" id="insight-form">
            @csrf
            @method('PATCH')
            <input name="key_insight" value="{{ $draft }}" maxlength="500" required aria-label="Key insight" placeholder="One sentence you want to read first next time…" @if ($draft !== null) autofocus @endif class="field min-w-0 flex-1 py-1">
            <button class="btn btn-sm btn-warning">Save</button>
        </form>
        @error('key_insight') <p class="field-error">{{ $message }}</p> @enderror
        <form action="{{ route('items.insight.suggest', $item) }}" method="POST" class="mt-2">
            @csrf
            <button class="text-xs text-amber-800 underline hover:text-amber-950 dark:text-amber-300">{{ $draft !== null ? 'New suggestion' : 'Let the AI suggest one' }}</button>
        </form>
    </section>
@endif
