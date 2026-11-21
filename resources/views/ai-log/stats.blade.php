@php
    $number = fn (int|string|null $value) => number_format((int) $value);
@endphp

<x-layout title="AI statistics">
    <div class="mb-6">
        <h1 class="page-title">AI statistics</h1>
        <p class="page-intro">Usage over the last {{ $periodDays }} days. Click a purpose to see its calls in the <a href="{{ route('ai-log.index') }}" class="underline">AI log</a>.</p>
    </div>

    <dl class="mb-8 grid grid-cols-2 gap-2 text-center">
        <div class="stat">
            <dd class="text-2xl font-semibold text-ink-900 tabular-nums dark:text-white">{{ $number($totals->calls) }}</dd>
            <dt class="meta">Calls @if ($totals->failures > 0)<span class="text-red-600">({{ $totals->failures }} failed)</span>@endif</dt>
        </div>
        <div class="stat">
            <dd class="text-2xl font-semibold text-ink-900 tabular-nums dark:text-white">{{ $number($totals->input_tokens + $totals->output_tokens) }}</dd>
            <dt class="meta">Tokens ({{ $number($totals->input_tokens) }} in / {{ $number($totals->output_tokens) }} out)</dt>
        </div>
    </dl>

    @if ($perPurpose->isNotEmpty())
        <section class="mb-8">
            <h2 class="eyebrow mb-2">By purpose</h2>
            <div class="card overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="text-left text-xs text-ink-500">
                        <tr>
                            <th class="px-3 py-2 font-medium">Purpose</th>
                            <th class="px-3 py-2 text-right font-medium">Calls</th>
                            <th class="px-3 py-2 text-right font-medium">Tokens in / out</th>
                            <th class="px-3 py-2 text-right font-medium">Avg. duration</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-ink-100 tabular-nums dark:divide-ink-800">
                        @foreach ($perPurpose as $row)
                            <tr>
                                <td class="px-3 py-2"><a href="{{ route('ai-log.index', ['name' => $row->name]) }}" class="text-baltic-500 hover:underline dark:text-baltic-300" title="View these calls in the log">{{ \App\Models\AiRequest::labelFor($row->name) }}</a></td>
                                <td class="px-3 py-2 text-right">{{ $number($row->calls) }} @if ($row->failures > 0)<span class="field-error">({{ $row->failures }}✕)</span>@endif</td>
                                <td class="px-3 py-2 text-right">{{ $number($row->input_tokens) }} / {{ $number($row->output_tokens) }}</td>
                                <td class="px-3 py-2 text-right">{{ $row->avg_duration_ms !== null ? number_format($row->avg_duration_ms / 1000, 1).' s' : '–' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </section>

        <section class="mb-8">
            <h2 class="eyebrow mb-2">By day (14 days)</h2>
            <div class="card overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="text-left text-xs text-ink-500">
                        <tr>
                            <th class="px-3 py-2 font-medium">Day</th>
                            <th class="px-3 py-2 text-right font-medium">Calls</th>
                            <th class="px-3 py-2 text-right font-medium">Tokens</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-ink-100 tabular-nums dark:divide-ink-800">
                        @foreach ($perDay as $row)
                            <tr>
                                <td class="px-3 py-2">{{ \Illuminate\Support\Carbon::parse($row->day)->translatedFormat('D j M') }}</td>
                                <td class="px-3 py-2 text-right">{{ $number($row->calls) }} @if ($row->failures > 0)<span class="field-error">({{ $row->failures }}✕)</span>@endif</td>
                                <td class="px-3 py-2 text-right">{{ $number($row->tokens) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </section>
    @endif

    @if ($perPurpose->isEmpty())
        <p class="empty-state">No AI calls in this period yet.</p>
    @endif
</x-layout>
