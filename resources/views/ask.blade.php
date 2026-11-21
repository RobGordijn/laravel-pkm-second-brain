<x-layout title="Ask your brain">
    <form action="{{ route('ask.store') }}" method="POST" class="mb-6" data-ask-form>
        @csrf
        <label for="question" class="page-title mb-3 block">Ask your brain</label>
        <div class="flex gap-2">
            <input
                id="question"
                name="question"
                value="{{ old('question', $question) }}"
                required
                autofocus
                placeholder="What did I find out about roof insulation again?"
                class="field min-w-0 flex-1 py-2"
            >
            <button class="btn btn-primary px-4 text-sm">Ask</button>
        </div>
        @error('question') <p class="field-error">{{ $message }}</p> @enderror
        <p class="mt-2 hidden text-xs text-ink-500" data-ask-loading>Searching and thinking…</p>
    </form>

    @if ($answer !== null)
        <x-ai-markdown :text="$answer" class="card p-4 text-sm" />

        @if ($sources->isNotEmpty())
            <form action="{{ route('ask.save') }}" method="POST" class="mt-2 flex items-center justify-end gap-2 text-xs text-ink-500">
                @csrf
                <input type="hidden" name="question" value="{{ $question }}">
                <input type="hidden" name="answer" value="{{ $answer }}">
                @foreach ($sources as $source)
                    <input type="hidden" name="source_ids[]" value="{{ $source->id }}">
                @endforeach
                <span>Useful answer?</span>
                <button class="btn btn-sm btn-secondary">Save as note</button>
            </form>
        @endif

        @if ($sources->isNotEmpty())
            <section class="mt-6">
                <h2 class="eyebrow mb-2">Notes used</h2>
                <ul class="space-y-1 text-sm">
                    @foreach ($sources as $source)
                        <li>
                            <a href="{{ route('items.show', $source) }}" class="hover:text-baltic-500 dark:hover:text-baltic-300">
                                <span class="text-ink-400">#{{ $source->id }}</span> {{ $source->title ?? Str::limit($source->raw, 80) }}
                            </a>
                        </li>
                    @endforeach
                </ul>
            </section>
        @endif
    @endif
</x-layout>
