<x-layout>
    <form id="capture-form" action="{{ route('items.store') }}" method="POST" class="card mb-8 p-3 focus-within:border-baltic-300 focus-within:ring-3 focus-within:ring-baltic-100 dark:focus-within:border-baltic-700 dark:focus-within:ring-baltic-900">
        @csrf
        <textarea
            name="raw"
            data-wiki-input
            rows="3"
            required
            placeholder="Dump your thought, link or to-do here…"
            class="block w-full resize-y bg-transparent px-1 py-1 text-base outline-none placeholder:text-ink-400 dark:placeholder:text-ink-500"
        ></textarea>
        <div class="mt-2 flex flex-wrap items-center gap-2 border-t border-ink-100 pt-3 dark:border-ink-800">
            <input
                name="why_saved"
                maxlength="1000"
                placeholder="Why are you saving this, or where did you leave off? (optional)"
                class="field min-w-0 flex-1 basis-60 shadow-none"
            >
            <span class="meta" data-capture-feedback></span>
            <a href="{{ route('items.write') }}" class="link-subtle">Write a note yourself</a>
            <button type="submit" class="btn btn-primary ml-auto">Save <kbd class="font-sans text-white/60 dark:text-ink-950/60">⌘↵</kbd></button>
        </div>
    </form>

    <form id="search-form" action="{{ route('items.index') }}" method="GET" class="mb-5 space-y-3">
        <div class="relative">
            <svg class="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-ink-400" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><circle cx="9" cy="9" r="6"/><path d="m14 14 4 4" stroke-linecap="round"/></svg>
            <input
                type="search"
                name="q"
                value="{{ $terms }}"
                placeholder="Search by words or meaning…"
                autocomplete="off"
                aria-label="Search"
                class="field py-2 pl-9"
            >
        </div>
        <div class="flex flex-wrap items-center gap-1.5 text-xs">
            <select name="type" class="field field-sm" aria-label="Type">
                <option value="">All types</option>
                @foreach ($types as $type)
                    <option value="{{ $type->value }}" @selected(request('type') === $type->value)>{{ $type->label() }}</option>
                @endforeach
            </select>
            <select name="tag" class="field field-sm" aria-label="Tag">
                <option value="">All tags</option>
                @foreach ($tags as $tag)
                    <option value="{{ $tag->slug }}" @selected(request('tag') === $tag->slug)>#{{ $tag->name }}</option>
                @endforeach
            </select>
            @if ($projects->isNotEmpty())
                <select name="project" class="field field-sm" aria-label="Project">
                    <option value="">All projects</option>
                    @foreach ($projects as $project)
                        <option value="{{ $project->slug }}" @selected(request('project') === $project->slug)>▸ {{ $project->name }}</option>
                    @endforeach
                </select>
            @endif
            <span class="mx-1 hidden h-4 w-px bg-ink-200 sm:block dark:bg-ink-700"></span>
            @foreach ([
                ['unfiled', '1', 'Unfiled', 'Notes without a project or area, still to be filed', request()->boolean('unfiled')],
                ['open_actions', '1', 'Open actions', null, request()->boolean('open_actions')],
                ['status', 'needs_feedback', 'Awaiting answer', null, request('status') === 'needs_feedback'],
                ['distill', '1', 'To distill', 'Notes you use often that have no key insight yet', request()->boolean('distill')],
                ['archived', '1', 'Archive', null, request()->boolean('archived')],
            ] as [$name, $value, $label, $hint, $isChecked])
                <label @if ($hint) title="{{ $hint }}" @endif class="inline-flex cursor-pointer items-center gap-1.5 rounded-full border border-ink-200 bg-white px-2.5 py-1 text-ink-600 select-none hover:border-ink-300 has-checked:border-baltic-500 has-checked:bg-baltic-500 has-checked:text-white has-focus-visible:ring-3 has-focus-visible:ring-baltic-100 dark:border-ink-700 dark:bg-ink-900 dark:text-ink-300 dark:has-checked:border-baltic-400 dark:has-checked:bg-baltic-400 dark:has-checked:text-ink-950">
                    <input type="checkbox" name="{{ $name }}" value="{{ $value }}" class="sr-only" @checked($isChecked)>
                    {{ $label }}
                    @if ($name === 'status' && $feedbackCount > 0)
                        <span class="rounded-full bg-amber-100 px-1.5 font-medium text-amber-800 dark:bg-amber-900 dark:text-amber-200">{{ $feedbackCount }}</span>
                    @endif
                </label>
            @endforeach
        </div>
    </form>

    <div id="item-list">
        @include('items._list')
    </div>
</x-layout>
