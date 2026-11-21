<x-layout :title="$item ? 'Write · '.($item->title ?? 'note') : 'New note'">
    <a href="{{ $item ? route('items.show', $item) : route('items.index') }}" class="back-link">← {{ $item ? 'Back to the note' : 'Inbox' }}</a>

    <form
        method="POST"
        action="{{ $item ? route('items.write.update', $item) : route('items.write.store') }}"
        class="mt-4"
        data-write-form
        @if ($item) data-autosave @else data-draft-key="brain:draft" @endif
    >
        @csrf
        @if ($item) @method('PUT') @endif

        <input
            name="title"
            value="{{ old('title', $item?->title) }}"
            maxlength="255"
            placeholder="Title (leave empty and the AI picks one)"
            aria-label="Title"
            class="w-full bg-transparent text-2xl font-semibold tracking-tight text-ink-900 outline-none placeholder:text-ink-300 dark:text-white dark:placeholder:text-ink-600"
        >
        @error('title') <span class="field-error">{{ $message }}</span> @enderror

        <div class="mt-4 flex items-center gap-1 text-sm">
            <button type="button" class="write-tab is-active" data-write-tab="write">Write</button>
            <button type="button" class="write-tab" data-write-tab="preview">Preview</button>
            <span class="meta ml-auto" data-write-status aria-live="polite"></span>
        </div>

        <div class="card mt-2">
            <textarea
                name="body"
                required
                data-write-body
                data-wiki-input
                @if ($item) data-wiki-except="{{ $item->id }}" @endif
                placeholder="Write in your own words. Markdown works; type [[ to link to another note."
                class="block min-h-[60vh] w-full resize-y rounded-xl bg-transparent p-4 text-base leading-relaxed outline-none placeholder:text-ink-400"
            >{{ old('body', $item?->body) }}</textarea>
            <div class="markdown hidden min-h-[60vh] p-4 text-base leading-relaxed" data-write-preview data-preview-url="{{ route('items.preview') }}"></div>
        </div>
        @error('body') <span class="field-error">{{ $message }}</span> @enderror

        <div class="mt-3 flex flex-wrap items-center gap-3">
            <p class="meta">
                @if ($item && ! $item->isAuthored())
                    Once you edit the text, it is yours: the AI still adds a summary, tags and links, but leaves your text alone.
                @else
                    The AI adds a summary, tags and links, but does not change your text.
                @endif
                <kbd>⌘S</kbd> saves.
            </p>
            <button class="btn btn-primary ml-auto">{{ $item ? 'Done' : 'Save' }}</button>
        </div>
    </form>

    @if ($item)
        <details class="disclosure mt-8" @if ($errors->hasAny(['summary', 'type', 'tags', 'why_saved', 'projects'])) open @endif>
            <summary>Properties <span class="meta font-normal">· type, tags, projects, summary, why saved</span></summary>
            <form action="{{ route('items.update', $item) }}" method="POST" class="mt-4 space-y-3 text-sm">
                @csrf
                @method('PATCH')
                <div class="grid gap-3 sm:grid-cols-[10rem_1fr]">
                    <label class="block">
                        <span class="field-label">Type</span>
                        <select name="type" class="field mt-1">
                            @foreach ($types as $type)
                                <option value="{{ $type->value }}" @selected(old('type', $item->type?->value ?? 'note') === $type->value)>{{ $type->label() }}</option>
                            @endforeach
                        </select>
                    </label>
                    <label class="block">
                        <span class="field-label">Tags <span class="font-normal text-ink-400">· comma-separated</span></span>
                        <input name="tags" value="{{ old('tags', $item->tags->pluck('name')->implode(', ')) }}" class="field mt-1">
                    </label>
                </div>
                @if ($projects->isNotEmpty())
                    <fieldset>
                        <legend class="field-label">Belongs to</legend>
                        <div class="mt-1.5 flex flex-wrap gap-1.5">
                            @foreach ($projects as $project)
                                <label class="inline-flex cursor-pointer items-center gap-1.5 rounded-full border border-ink-200 bg-white px-2.5 py-1 text-xs text-ink-600 select-none hover:border-ink-300 has-checked:border-cerulean-400 has-checked:bg-cerulean-100 has-checked:text-cerulean-700 has-focus-visible:ring-3 has-focus-visible:ring-baltic-100 dark:border-ink-700 dark:bg-ink-900 dark:text-ink-300 dark:has-checked:border-cerulean-500 dark:has-checked:bg-cerulean-800 dark:has-checked:text-cerulean-100">
                                    <input type="checkbox" name="projects[]" value="{{ $project->id }}" class="sr-only" @checked(in_array($project->id, old('projects', $item->projects->modelKeys())))>
                                    {{ $project->name }} <span class="opacity-60">{{ Str::lower($project->kind->label()) }}</span>
                                </label>
                            @endforeach
                        </div>
                    </fieldset>
                @endif
                <label class="block">
                    <span class="field-label">Summary</span>
                    <textarea name="summary" rows="2" maxlength="2000" class="field mt-1">{{ old('summary', $item->summary) }}</textarea>
                </label>
                <label class="block">
                    <span class="field-label">Why saved / where I left off</span>
                    <input name="why_saved" value="{{ old('why_saved', $item->why_saved) }}" maxlength="1000" class="field mt-1">
                </label>
                <button class="btn btn-primary">Save properties</button>
            </form>
        </details>
    @endif
</x-layout>
