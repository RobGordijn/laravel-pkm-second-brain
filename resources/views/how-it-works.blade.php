@php
    /*
     * Coordinates are in the SVG's own 790 × 1310 space; the drawing scales with the page width.
     * kind: "you" = something the author does, "ai" = done by the AI, "place" = where a note ends up.
     */
    $bands = [
        ['y' => 0, 'h' => 180, 'name' => 'Capture', 'code' => 'CODE · C', 'hint' => ['quickly, without', 'overthinking']],
        ['y' => 190, 'h' => 410, 'name' => 'Process', 'code' => 'by the AI', 'hint' => ['happens by itself,', 'in the background']],
        ['y' => 610, 'h' => 270, 'name' => 'Organize', 'code' => 'CODE · O', 'hint' => ['by usefulness,', 'not by topic']],
        ['y' => 890, 'h' => 200, 'name' => 'Distill', 'code' => 'CODE · D', 'hint' => ['what you use', 'gets sharper']],
        ['y' => 1100, 'h' => 210, 'name' => 'Express', 'code' => 'CODE · E', 'hint' => ['knowledge becomes', 'action and output']],
    ];

    $nodes = [
        ['x' => 130, 'y' => 30, 'w' => 170, 'h' => 52, 'kind' => 'you', 'title' => 'Inbox', 'lines' => ['dump it, ⌘+Enter'], 'href' => route('items.index')],
        ['x' => 330, 'y' => 22, 'w' => 160, 'h' => 52, 'kind' => 'you', 'title' => 'iOS Shortcut', 'lines' => ['via the capture API']],
        ['x' => 560, 'y' => 30, 'w' => 190, 'h' => 52, 'kind' => 'you', 'title' => 'Write', 'lines' => ['own words, [[links]]'], 'href' => route('items.write')],
        ['x' => 350, 'y' => 100, 'w' => 200, 'h' => 66, 'kind' => 'you', 'title' => 'Note', 'lines' => ['thought, link, to-do or text', '+ optional: why you saved it'], 'badge' => $counts['items'].' notes'],

        ['x' => 350, 'y' => 215, 'w' => 200, 'h' => 52, 'kind' => 'ai', 'title' => 'Read link', 'lines' => ['fetches the text of the page']],
        ['x' => 350, 'y' => 295, 'w' => 200, 'h' => 66, 'kind' => 'ai', 'title' => 'Classify', 'lines' => ['kind: note, idea, to-do…', 'and: is this too vague?']],
        ['x' => 600, 'y' => 340, 'w' => 150, 'h' => 66, 'kind' => 'you', 'title' => 'Follow-up', 'lines' => ['too vague? you add', 'context in one line'], 'badge' => $counts['needsFeedback'] ? $counts['needsFeedback'].' open' : null, 'href' => route('items.index', ['status' => 'needs_feedback'])],
        ['x' => 350, 'y' => 390, 'w' => 200, 'h' => 80, 'kind' => 'ai', 'title' => 'Analyze', 'lines' => ['title, summary, tags, actions,', 'a suggested project;', 'text you wrote stays as is']],
        ['x' => 350, 'y' => 505, 'w' => 200, 'h' => 66, 'kind' => 'ai', 'title' => 'Find connections', 'lines' => ['compares meaning; AI picks', 'truly related notes']],

        ['x' => 140, 'y' => 640, 'w' => 170, 'h' => 66, 'kind' => 'ai', 'title' => 'Weekly digest', 'lines' => ['spots what belongs together', 'and suggests a project'], 'href' => route('digests.index')],
        ['x' => 350, 'y' => 640, 'w' => 200, 'h' => 66, 'kind' => 'you', 'title' => 'Review', 'lines' => ['you choose, the AI suggestion', 'is ready; or create a new one'], 'badge' => $counts['awaitingReview'] ? $counts['awaitingReview'].' waiting' : null, 'href' => route('review.index')],
        ['x' => 580, 'y' => 640, 'w' => 170, 'h' => 66, 'kind' => 'ai', 'title' => 'Tidy tags', 'lines' => ['AI finds duplicate tags,', 'you merge them'], 'href' => route('tags.index')],
        ['x' => 140, 'y' => 770, 'w' => 190, 'h' => 66, 'kind' => 'place', 'title' => 'Project / area', 'lines' => ['goal or ongoing concern;', 'actions first in Today'], 'badge' => $counts['projects'].' active', 'href' => route('projects.index')],
        ['x' => 350, 'y' => 770, 'w' => 200, 'h' => 66, 'kind' => 'place', 'title' => 'Reference', 'lines' => ['no project; findable', 'through tags and search'], 'href' => route('items.index')],
        ['x' => 570, 'y' => 770, 'w' => 180, 'h' => 66, 'kind' => 'place', 'title' => 'Archive', 'lines' => ['out of sight, not gone;', '‘Archive’ filter in the inbox'], 'badge' => $counts['archived'] ? $counts['archived'].' archived' : null, 'href' => route('items.index', ['archived' => 1])],

        ['x' => 140, 'y' => 925, 'w' => 210, 'h' => 66, 'kind' => 'you', 'title' => 'You use a note', 'lines' => ['opened, or cited in', 'an answer: counter +1']],
        ['x' => 140, 'y' => 1010, 'w' => 210, 'h' => 66, 'kind' => 'you', 'title' => 'In your own words', 'lines' => ['rewrite it through Edit;', 'the AI then leaves your text']],
        ['x' => 470, 'y' => 1010, 'w' => 280, 'h' => 66, 'kind' => 'ai', 'title' => 'Let the AI rewrite it', 'lines' => ['hand it back: the AI reworks', 'your current text']],
        ['x' => 470, 'y' => 925, 'w' => 280, 'h' => 66, 'kind' => 'ai', 'title' => 'Key insight in one line', 'lines' => ['after 3 uses the AI suggests one;', 'you make it your own'], 'badge' => $counts['toDistill'] ? $counts['toDistill'].' to distill' : null, 'href' => route('items.index', ['distill' => 1])],

        ['x' => 140, 'y' => 1135, 'w' => 180, 'h' => 66, 'kind' => 'you', 'title' => 'Today', 'lines' => ['actions from your dumps,', 'projects first'], 'badge' => $counts['openActions'] ? $counts['openActions'].' open' : null, 'href' => route('today')],
        ['x' => 350, 'y' => 1135, 'w' => 200, 'h' => 66, 'kind' => 'ai', 'title' => 'Ask your brain', 'lines' => ['searches notes, the AI answers', 'with sources, like [#12]'], 'href' => route('ask.index')],
        ['x' => 580, 'y' => 1135, 'w' => 170, 'h' => 66, 'kind' => 'you', 'title' => 'Save as synthesis', 'lines' => ['a new note, linked', 'to its sources']],
        ['x' => 140, 'y' => 1225, 'w' => 180, 'h' => 66, 'kind' => 'you', 'title' => 'Export', 'lines' => ['everything as markdown,', 'opens in Obsidian'], 'href' => route('export')],
    ];

    $boxClasses = [
        'you' => 'fill-white stroke-ink-300 dark:fill-ink-900 dark:stroke-ink-600',
        'ai' => 'fill-cerulean-50 stroke-cerulean-300 dark:fill-cerulean-950 dark:stroke-cerulean-700',
        'place' => 'fill-sage-50 stroke-sage-300 dark:fill-sage-950 dark:stroke-sage-800',
    ];

    $titleClasses = [
        'you' => 'fill-ink-800 dark:fill-ink-100',
        'ai' => 'fill-cerulean-900 dark:fill-cerulean-100',
        'place' => 'fill-sage-900 dark:fill-sage-100',
    ];

    /*
     * Arrows: [path, dashed?, label, label x, label y, label anchor].
     */
    $edges = [
        ['M215 82 C215 112, 300 133, 346 133'],
        ['M410 74 V96'],
        ['M655 82 C655 112, 600 133, 554 133'],
        ['M450 166 V211'],
        ['M450 267 V291'],
        ['M450 361 V386'],
        ['M550 430 H675 V410', true, 'too vague', 612, 446, 'middle'],
        ['M675 340 V320 H554', true, 'your answer: again', 652, 313, 'middle'],
        ['M450 470 V501'],
        ['M450 571 V636', false, 'new in your brain', 458, 610, 'start'],
        ['M450 706 C450 740, 235 738, 235 766'],
        ['M450 706 V766'],
        ['M450 706 C450 740, 660 738, 660 766'],
        ['M200 706 V766', true, 'create project', 194, 742, 'end'],
        ['M235 836 V856 H660 V840', true, 'project completed: its notes go along', 448, 872, 'middle'],
        ['M350 958 H466', false, '3×', 408, 950, 'middle'],
        ['M350 1043 H466', true, 'back', 408, 1035, 'middle'],
        ['M550 1168 H576'],
        ['M665 1201 V1240 H770 V538 H554', true, 'back into the brain', 717, 1256, 'middle'],
    ];
@endphp

<x-layout title="How it works">
    <div class="mb-4">
        <h1 class="page-title">How your brain works</h1>
        <p class="page-intro">
            From a loose dump or your own text to something useful, in five steps. The numbers are live; click a block to go there.
        </p>
    </div>

    <div class="mb-4 flex flex-wrap items-center gap-x-4 gap-y-2 text-xs text-ink-600 dark:text-ink-400">
        <span class="flex items-center gap-1.5"><span class="size-3 rounded-sm border border-ink-300 bg-white dark:border-ink-600 dark:bg-ink-900"></span> You do something</span>
        <span class="flex items-center gap-1.5"><span class="size-3 rounded-sm border border-cerulean-300 bg-cerulean-50 dark:border-cerulean-700 dark:bg-cerulean-950"></span> The AI does it</span>
        <span class="flex items-center gap-1.5"><span class="size-3 rounded-sm border border-sage-300 bg-sage-50 dark:border-sage-800 dark:bg-sage-950"></span> Where a note ends up</span>
        <span class="flex items-center gap-1.5"><svg class="h-2 w-6" viewBox="0 0 24 8" aria-hidden="true"><path d="M0 4 H24" class="stroke-ink-400" stroke-width="2" stroke-dasharray="4 3" /></svg> Sometimes, or later</span>
    </div>

    <p class="mb-2 text-xs text-ink-400 sm:hidden">Swipe sideways to see the whole diagram →</p>

    <div class="-mx-4 overflow-x-auto px-4 sm:mx-0 sm:px-0">
        <svg viewBox="0 0 790 1310" class="w-full min-w-[640px] font-sans" role="img" aria-labelledby="flow-title flow-desc">
            <title id="flow-title">The process of your second brain</title>
            <desc id="flow-desc">A dump or a note you wrote yourself is read, classified, analyzed and linked by the AI. Then you file it under a project, area, reference or archive, or create a new project right away. What you use often gets a one-sentence key insight and can be rewritten in your own words. Answers to your questions can be saved, and they go back into the brain. Everything can be exported as markdown.</desc>

            <defs>
                <marker id="arrow" viewBox="0 0 10 10" refX="9" refY="5" markerWidth="7" markerHeight="7" orient="auto-start-reverse">
                    <path d="M0 0 L10 5 L0 10 z" class="fill-ink-400 dark:fill-ink-500" />
                </marker>
            </defs>

            @foreach ($bands as $index => $band)
                <rect x="0" y="{{ $band['y'] }}" width="790" height="{{ $band['h'] }}" rx="14" @class(['fill-ink-100 dark:fill-ink-900/60' => $index % 2 === 0, 'fill-transparent' => $index % 2 === 1]) />
                <circle cx="31" cy="{{ $band['y'] + 30 }}" r="13" class="fill-ink-800 dark:fill-ink-200" />
                <text x="31" y="{{ $band['y'] + 34.5 }}" text-anchor="middle" class="fill-white text-[13px] font-semibold dark:fill-ink-900">{{ $index + 1 }}</text>
                <text x="16" y="{{ $band['y'] + 64 }}" class="fill-ink-800 text-[14px] font-semibold dark:fill-ink-100">{{ $band['name'] }}</text>
                <text x="16" y="{{ $band['y'] + 79 }}" class="fill-ink-400 text-[10px] tracking-wide uppercase">{{ $band['code'] }}</text>
                @foreach ($band['hint'] as $line)
                    <text x="16" y="{{ $band['y'] + 100 + $loop->index * 13 }}" class="fill-ink-500 text-[10.5px] dark:fill-ink-400">{{ $line }}</text>
                @endforeach
            @endforeach

            @foreach ($edges as $edge)
                <path d="{{ $edge[0] }}" fill="none" stroke-width="1.6" marker-end="url(#arrow)" class="stroke-ink-400 dark:stroke-ink-500" @if ($edge[1] ?? false) stroke-dasharray="5 4" @endif />
                @isset($edge[2])
                    <text x="{{ $edge[3] }}" y="{{ $edge[4] }}" text-anchor="{{ $edge[5] }}" class="fill-ink-500 text-[10.5px] italic dark:fill-ink-400">{{ $edge[2] }}</text>
                @endisset
            @endforeach

            @foreach ($nodes as $node)
                @php($tag = isset($node['href']) ? 'a' : 'g')
                <{{ $tag }} @isset($node['href']) href="{{ $node['href'] }}" class="group" @endisset>
                    <rect x="{{ $node['x'] }}" y="{{ $node['y'] }}" width="{{ $node['w'] }}" height="{{ $node['h'] }}" rx="10" stroke-width="1.2" @class([$boxClasses[$node['kind']], 'transition group-hover:stroke-ink-500 dark:group-hover:stroke-ink-300' => isset($node['href'])]) />
                    <text x="{{ $node['x'] + 12 }}" y="{{ $node['y'] + 22 }}" @class(['text-[13px] font-semibold', $titleClasses[$node['kind']]])>
                        @if ($node['kind'] === 'ai')<tspan class="fill-cerulean-500 dark:fill-cerulean-400">✦ </tspan>@endif{{ $node['title'] }}@isset($node['href'])<tspan class="fill-ink-400 opacity-0 transition group-hover:opacity-100"> →</tspan>@endisset
                    </text>
                    @foreach ($node['lines'] as $line)
                        <text x="{{ $node['x'] + 12 }}" y="{{ $node['y'] + 40 + $loop->index * 14 }}" class="fill-ink-500 text-[11px] dark:fill-ink-400">{{ $line }}</text>
                    @endforeach
                </{{ $tag }}>

                @if (filled($node['badge'] ?? null))
                    @php($badgeWidth = mb_strlen((string) $node['badge']) * 6.2 + 14)
                    <rect x="{{ $node['x'] + $node['w'] - $badgeWidth - 8 }}" y="{{ $node['y'] - 9 }}" width="{{ $badgeWidth }}" height="18" rx="9" class="fill-amber-100 stroke-amber-300 dark:fill-amber-900 dark:stroke-amber-700" />
                    <text x="{{ $node['x'] + $node['w'] - $badgeWidth / 2 - 8 }}" y="{{ $node['y'] + 3.5 }}" text-anchor="middle" class="fill-amber-900 text-[10.5px] font-medium tabular-nums dark:fill-amber-100">{{ $node['badge'] }}</text>
                @endif
            @endforeach
        </svg>
    </div>

    <section class="mt-10">
        <h2 class="eyebrow mb-2">What you can do with a single note</h2>
        <dl class="card-list text-sm">
            @foreach ([
                'Edit' => 'Opens the writing mode for title and text; type [[ to link to another note. Once you edit the text, it is yours: the AI still adds a summary, tags and links, but leaves your text alone. Under ‘Properties’ you change the type, tags, projects, summary and why you saved it.',
                'Let the AI rewrite it' => 'In the ⋯ menu, only for a note you wrote yourself: the AI reworks your current text and chooses the title and type again.',
                'Process again' => 'In the ⋯ menu: the AI runs the analysis once more, for example after processing failed. Text you wrote yourself stays as is.',
                'Key insight' => 'One sentence you want to read first next time. After a few uses the app asks for it; you can add one earlier through ‘Add key insight’ in the ⋯ menu. The AI can suggest one.',
                'Connections' => 'One list with your own [[links]], notes that link to this one, and related notes suggested by the AI; you confirm (✓) or reject (✕) those last ones.',
                'Original dump' => 'What you originally entered, under Details or through the ⋯ menu.',
                'Archive' => 'In the ⋯ menu. Out of sight, not gone; restore it through the ‘Archive’ filter in the inbox.',
                'Delete' => 'Gone for good, including its actions and connections.',
            ] as $option => $explanation)
                <div class="flex flex-col gap-0.5 px-4 py-2.5 sm:flex-row sm:gap-4">
                    <dt class="shrink-0 font-medium text-ink-900 sm:w-56 dark:text-white">{{ $option }}</dt>
                    <dd class="text-ink-600 dark:text-ink-400">{{ $explanation }}</dd>
                </div>
            @endforeach
        </dl>
    </section>

    <p class="mt-6 text-xs text-ink-500">
        Everything the AI does can be reviewed in the <a href="{{ route('ai-log.index') }}" class="underline">AI log</a>, with usage per purpose in the <a href="{{ route('ai-log.stats') }}" class="underline">AI statistics</a>.
    </p>

    <p class="mt-2 text-xs text-ink-500">
        Based on the CODE method from
        <a href="https://fortelabs.com/blog/basboverview/" target="_blank" rel="noopener noreferrer" class="underline">Building a Second Brain</a>
        by Tiago Forte: capture, organize, distill and express.
    </p>
</x-layout>
