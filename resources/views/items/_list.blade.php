<div class="space-y-3" data-item-list>
    @forelse ($items as $item)
        @include('items._card', ['item' => $item])
    @empty
        <p class="empty-state" data-empty>
            {{ $terms !== '' ? 'Nothing found for “'.$terms.'”.' : 'Nothing dumped yet. Start above.' }}
        </p>
    @endforelse
</div>
