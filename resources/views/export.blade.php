<x-layout title="Export">
    <div class="mb-6">
        <h1 class="page-title">Export</h1>
        <p class="page-intro">Your whole brain as plain markdown files. Always yours, readable without this app.</p>
    </div>

    <section class="card flex flex-wrap items-center gap-4 p-5">
        <div class="grid size-12 shrink-0 place-items-center rounded-xl bg-baltic-50 text-baltic-500 dark:bg-baltic-800 dark:text-baltic-100">
            <svg class="size-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 4v11M7 10l5 5 5-5M5 20h14"/></svg>
        </div>
        <div class="min-w-0 flex-1">
            <p class="font-semibold text-ink-900 dark:text-white">brain-export-{{ now()->toDateString() }}.zip</p>
            <p class="meta">{{ $itemCount }} {{ $itemCount === 1 ? 'note' : 'notes' }} · {{ $projectCount }} {{ $projectCount === 1 ? 'project or area' : 'projects and areas' }}, including the archive</p>
        </div>
        <a href="{{ route('export.download') }}" class="btn btn-primary px-4 py-2 text-sm">Download export</a>
    </section>

    <section class="mt-8">
        <h2 class="eyebrow mb-2">What's inside</h2>
        <div class="card-list text-sm">
            <div class="flex gap-4 px-4 py-3">
                <code class="w-28 shrink-0 font-mono text-xs text-baltic-500 dark:text-baltic-300">notes/</code>
                <p>One file per note, such as <code class="font-mono text-xs">12-bathroom-tiles.md</code>. The text is exactly what you see: your own text, or the AI's version with your original dump below it.</p>
            </div>
            <div class="flex gap-4 px-4 py-3">
                <code class="w-28 shrink-0 font-mono text-xs text-baltic-500 dark:text-baltic-300">projects/</code>
                <p>One file per project or area, with its goal and a list of links to the notes that belong to it.</p>
            </div>
            <div class="flex gap-4 px-4 py-3">
                <code class="w-28 shrink-0 font-mono text-xs text-baltic-500 dark:text-baltic-300">front matter</code>
                <p>The metadata at the top of each file: type, tags, projects, source URL, summary, key insight, why you saved it, your answer to a question from the AI, and the dates.</p>
            </div>
            <div class="flex gap-4 px-4 py-3">
                <code class="w-28 shrink-0 font-mono text-xs text-baltic-500 dark:text-baltic-300">[[links]]</code>
                <p>Links between notes, confirmed related notes and projects become <code class="font-mono text-xs">[[...]]</code> links, so the connections stay clickable.</p>
            </div>
            <div class="flex gap-4 px-4 py-3">
                <code class="w-28 shrink-0 font-mono text-xs text-baltic-500 dark:text-baltic-300">actions</code>
                <p>Actions appear as a checklist (<code class="font-mono text-xs">- [ ]</code>) in the note, with their deadline.</p>
            </div>
        </div>
    </section>

    <section class="mt-8">
        <h2 class="eyebrow mb-2">How to use it</h2>
        <ol class="card list-inside list-decimal space-y-1.5 p-4 text-sm marker:text-ink-400">
            <li>Download the zip and unpack it.</li>
            <li>Open the folder as a <em>vault</em> in Obsidian, or in any other markdown editor.</li>
            <li>Notes, projects and links work right away; searching by tag works through the front matter.</li>
        </ol>
        <p class="meta mt-3">Not included in the export: suggested (not yet confirmed) links, the AI log and the search index. Those belong to the app, not to your knowledge.</p>
    </section>
</x-layout>
