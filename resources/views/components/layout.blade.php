@props(['title' => null])

@php
    /*
     * Sidebar sections: [route, label, active route patterns, icon path (24×24 outline)].
     */
    $navigation = [
        'Capture' => [
            ['items.index', 'Inbox', ['items.index', 'items.show', 'items.write.edit'], 'M3 13h4l2 3h6l2-3h4M5 5h14l2 8v6H3v-6z'],
            ['items.write', 'Write', ['items.write'], 'M4 20h4L19 9l-4-4L4 16v4zM13.5 6.5l4 4'],
        ],
        'Organize' => [
            ['review.index', 'Review', ['review.*'], 'M4 6h16M4 12h10M4 18h6M17 15l3 3-3 3'],
            ['projects.index', 'Projects', ['projects.*'], 'M3 7h7l2 2h9v10H3zM3 7V5h6'],
            ['tags.index', 'Tags', ['tags.*'], 'M4 4h7l9 9-7 7-9-9zM8 8h.01'],
        ],
        'Use' => [
            ['today', 'Today', ['today'], 'M5 12l4 4L19 6'],
            ['ask.index', 'Ask', ['ask.*'], 'M8 9a4 4 0 1 1 5 3.9V15M12 19h.01'],
            ['digests.index', 'Digest', ['digests.*'], 'M5 4h14v16H5zM9 8h6M9 12h6M9 16h3'],
        ],
        'System' => [
            ['export', 'Export', ['export*'], 'M12 4v11M7 10l5 5 5-5M5 20h14'],
            ['ai-log.stats', 'AI statistics', ['ai-log.stats'], 'M5 20V10M12 20V4M19 20v-7'],
            ['ai-log.index', 'AI-log', ['ai-log.index'], 'M5 6h14M5 12h14M5 18h9'],
            ['how-it-works', 'How it works', ['how-it-works'], 'M12 21a9 9 0 1 0 0-18 9 9 0 0 0 0 18zM12 11v5M12 8h.01'],
        ],
    ];
@endphp

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title ? $title.' · ' : '' }}Brain</title>
    <link rel="icon" href="/favicon.svg" type="image/svg+xml">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-ink-50 text-ink-800 antialiased dark:bg-ink-950 dark:text-ink-200">
    <input type="checkbox" id="nav-toggle" class="peer sr-only" aria-hidden="true">

    {{-- Mobile top bar --}}
    <header class="sticky top-0 z-30 flex items-center gap-3 border-b border-white/10 bg-ink-950 px-4 py-2.5 text-white lg:hidden">
        <label for="nav-toggle" class="-ml-1 grid size-9 cursor-pointer place-items-center rounded-md hover:bg-white/10" aria-label="Menu">
            <svg class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M4 7h16M4 12h16M4 17h16"/></svg>
        </label>
        <a href="{{ route('items.index') }}" class="flex items-center gap-2 font-semibold tracking-tight">
            <x-logo class="size-7" />
            Brain
        </a>
        @if ($reviewCount > 0)
            <a href="{{ route('review.index') }}" class="ml-auto rounded-full bg-lime-400 px-2 text-xs font-semibold text-ink-950 tabular-nums">{{ $reviewCount }} to review</a>
        @endif
    </header>

    {{-- Backdrop for the mobile menu --}}
    <label for="nav-toggle" class="fixed inset-0 z-30 hidden bg-ink-950/60 peer-checked:block lg:hidden!" aria-hidden="true"></label>

    {{-- On large screens the sidebar and content form one centred block: 15rem menu + 2rem gap + 48rem content. --}}
    <aside class="fixed inset-y-0 left-0 z-40 lg:left-[max(0px,calc((100vw-65rem)/2))] flex w-60 -translate-x-full flex-col overflow-y-auto bg-ink-950 text-white transition-transform peer-checked:translate-x-0 lg:translate-x-0 dark:border-r dark:border-white/5">
        <a href="{{ route('items.index') }}" class="flex items-center gap-2.5 px-5 pt-5 pb-6 text-lg font-semibold tracking-tight">
            <x-logo class="size-9" />
            Brain
        </a>
        <nav class="flex-1 space-y-6 px-3 pb-6 text-sm">
            @foreach ($navigation as $section => $links)
                <div>
                    <p class="px-2 pb-1.5 text-[11px] font-semibold tracking-wider text-white/35 uppercase">{{ $section }}</p>
                    <ul class="space-y-0.5">
                        @foreach ($links as [$route, $label, $patterns, $icon])
                            @php($isActive = request()->routeIs(...$patterns))
                            <li>
                                <a href="{{ route($route) }}" @class([
                                    'flex items-center gap-2.5 rounded-md px-2 py-1.5 transition-colors',
                                    'bg-white/10 font-medium text-white' => $isActive,
                                    'text-white/65 hover:bg-white/5 hover:text-white' => ! $isActive,
                                ]) @if ($isActive) aria-current="page" @endif>
                                    <svg @class(['size-4 shrink-0', 'text-lime-400' => $isActive, 'text-white/40' => ! $isActive]) viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="{{ $icon }}"/></svg>
                                    {{ $label }}
                                    @if ($route === 'review.index' && $reviewCount > 0)
                                        <span class="ml-auto rounded-full bg-lime-400 px-1.5 text-xs font-semibold text-ink-950 tabular-nums">{{ $reviewCount }}</span>
                                    @endif
                                </a>
                            </li>
                        @endforeach
                    </ul>
                </div>
            @endforeach
        </nav>
    </aside>

    <main class="px-4 py-8 lg:pr-8 lg:pl-[calc(max(0px,(100vw-65rem)/2)+17rem)]">
        <div class="mx-auto max-w-3xl lg:mx-0">
        @if (session('status'))
            <p class="mb-6 flex items-center gap-2 rounded-lg border border-sage-200 bg-sage-50 px-3 py-2 text-sm text-sage-800 dark:border-sage-800 dark:bg-sage-900 dark:text-sage-100">
                <span class="text-sage-500 dark:text-sage-300">✓</span>
                {{ session('status') }}
            </p>
        @endif

        {{ $slot }}
        </div>
    </main>
</body>
</html>
