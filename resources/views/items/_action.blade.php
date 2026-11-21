<li class="flex items-start gap-2 text-sm">
    <input
        type="checkbox"
        class="mt-0.5 size-4 shrink-0 cursor-pointer"
        data-toggle-action="{{ route('actions.update', $action) }}"
        @checked($action->isDone())
    >
    <div class="min-w-0 flex-1">
        <span @class(['line-through text-ink-400' => $action->isDone()]) data-action-text>{{ $action->text }}</span>
        @if (! empty($showItem))
            <a href="{{ route('items.show', $action->item) }}" class="block truncate text-xs text-ink-400 hover:underline">
                @if ($action->item->relationLoaded('projects') && $action->item->projects->isNotEmpty())
                    <span class="text-cerulean-500 dark:text-cerulean-300">▸ {{ $action->item->projects->pluck('name')->implode(', ') }}</span> ·
                @endif
                {{ $action->item->title ?? Str::limit($action->item->raw, 60) }}
            </a>
        @endif
    </div>
    @if ($action->due_on)
        <span @class([
            'badge shrink-0',
            'bg-red-50 text-red-700 dark:bg-red-950 dark:text-red-300' => ! $action->isDone() && $action->due_on->isPast() && ! $action->due_on->isToday(),
            'bg-ink-100 text-ink-500 dark:bg-ink-800 dark:text-ink-400' => $action->isDone() || ! $action->due_on->isPast() || $action->due_on->isToday(),
        ])>{{ $action->due_on->translatedFormat('j M') }}</span>
    @endif
</li>
