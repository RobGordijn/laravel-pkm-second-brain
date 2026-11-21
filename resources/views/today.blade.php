<x-layout title="Today">
    @foreach (['Today & overdue' => $dueNow, 'Scheduled' => $upcoming, 'Someday' => $someday] as $heading => $actions)
        @if ($actions->isNotEmpty())
            <section class="mb-8">
                <h2 class="eyebrow mb-2">{{ $heading }}</h2>
                <ul class="card space-y-2 p-4">
                    @foreach ($actions as $action)
                        @include('items._action', ['action' => $action, 'showItem' => true])
                    @endforeach
                </ul>
            </section>
        @endif
    @endforeach

    @if ($dueNow->isEmpty() && $upcoming->isEmpty() && $someday->isEmpty())
        <p class="empty-state">No open actions. Nice work.</p>
    @endif
</x-layout>
