@php
    $number = fn (int|string|null $value) => number_format((int) $value);
@endphp

<x-layout title="AI log">
    <div class="mb-6">
        <h1 class="page-title">AI log</h1>
        <p class="page-intro">Every AI call with its request and response. Entries older than {{ $periodDays }} days are pruned automatically.</p>
    </div>

    <section>
        <div class="mb-4 flex flex-wrap items-center gap-1.5 text-xs">
            <a href="{{ route('ai-log.index', request()->only('failed')) }}" @class(['badge border px-2.5 py-1', 'border-baltic-500 bg-baltic-500 text-white dark:border-baltic-400 dark:bg-baltic-400 dark:text-ink-950' => ! request('name'), 'border-ink-200 bg-white hover:border-ink-300 dark:border-ink-700 dark:bg-ink-900' => request('name')])>All</a>
            @foreach ($names as $name)
                <a href="{{ route('ai-log.index', ['name' => $name] + request()->only('failed')) }}" @class(['badge border px-2.5 py-1', 'border-baltic-500 bg-baltic-500 text-white dark:border-baltic-400 dark:bg-baltic-400 dark:text-ink-950' => request('name') === $name, 'border-ink-200 bg-white hover:border-ink-300 dark:border-ink-700 dark:bg-ink-900' => request('name') !== $name])>{{ \App\Models\AiRequest::labelFor($name) }}</a>
            @endforeach
            <a href="{{ request('failed') ? route('ai-log.index', request()->only('name')) : route('ai-log.index', ['failed' => 1] + request()->only('name')) }}" @class(['badge ml-auto border px-2.5 py-1', 'border-red-600 bg-red-600 text-white' => request('failed'), 'border-ink-200 bg-white text-red-600 hover:border-red-300 dark:border-ink-700 dark:bg-ink-900 dark:text-red-400' => ! request('failed')])>Failed only</a>
        </div>

        @forelse ($requests as $aiRequest)
            <details class="card mb-2 text-sm">
                <summary class="flex cursor-pointer list-none flex-wrap items-center gap-x-3 gap-y-1 px-3 py-2">
                    <span class="w-28 shrink-0 text-xs text-ink-500 tabular-nums">{{ $aiRequest->created_at->format('M j H:i:s') }}</span>
                    <span @class(['font-medium', 'text-red-600' => $aiRequest->failed()])>{{ $aiRequest->label() }}</span>
                    @if ($aiRequest->item)
                        <span class="min-w-0 flex-1 truncate text-xs text-ink-500">#{{ $aiRequest->item->id }} {{ $aiRequest->item->title ?? Str::limit($aiRequest->item->raw, 50) }}</span>
                    @else
                        <span class="flex-1"></span>
                    @endif
                    <span class="text-xs text-ink-500 tabular-nums">
                        {{ $number($aiRequest->input_tokens) }}→{{ $number($aiRequest->output_tokens) }} tok
                        @if ($aiRequest->duration_ms) · {{ number_format($aiRequest->duration_ms / 1000, 1) }} s @endif
                    </span>
                </summary>
                <div class="space-y-3 border-t border-ink-100 px-3 py-3 dark:border-ink-800">
                    <p class="meta">
                        {{ $aiRequest->provider }} · {{ $aiRequest->model }}
                        @if ($aiRequest->item) · <a href="{{ route('items.show', $aiRequest->item) }}" class="underline">open note</a> @endif
                    </p>
                    @if ($aiRequest->error)
                        <p class="rounded-md bg-red-50 px-2 py-1 text-xs text-red-800 dark:bg-red-950 dark:text-red-200">{{ $aiRequest->error }}</p>
                    @endif
                    <div>
                        <p class="mb-1 text-xs font-medium text-ink-500">Request</p>
                        <pre class="max-h-64 overflow-auto rounded-md bg-ink-50 p-2 text-xs whitespace-pre-wrap dark:bg-ink-950">{{ $aiRequest->request }}</pre>
                    </div>
                    @if ($aiRequest->response !== null)
                        <div>
                            <p class="mb-1 text-xs font-medium text-ink-500">Response</p>
                            <pre class="max-h-64 overflow-auto rounded-md bg-ink-50 p-2 text-xs whitespace-pre-wrap dark:bg-ink-950">{{ $aiRequest->response }}</pre>
                        </div>
                    @endif
                </div>
            </details>
        @empty
            <p class="empty-state">No AI calls logged yet.</p>
        @endforelse

        <div class="mt-4">{{ $requests->links() }}</div>
    </section>
</x-layout>
