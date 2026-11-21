<?php

namespace App\Jobs;

use App\Ai\Agents\LinkJudge;
use App\Enums\LinkState;
use App\Listeners\LogAiRequests;
use App\Models\Item;
use App\Models\ItemLink;
use Illuminate\Contracts\Queue\ShouldBeUniqueUntilProcessing;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Laravel\Ai\Embeddings;
use Throwable;

/**
 * (Re)generates an item's embedding and suggests links to genuinely related items.
 *
 * Vector similarity finds candidates; an LLM judge decides which are really related and why.
 * Unique only while queued: an edit made while the job runs queues a fresh run with the new text.
 */
class LinkItem implements ShouldBeUniqueUntilProcessing, ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /**
     * @var list<int>
     */
    public array $backoff = [10, 60];

    public function __construct(public Item $item) {}

    public function uniqueId(): string
    {
        return (string) $this->item->id;
    }

    public function handle(): void
    {
        LogAiRequests::forItem($this->item->id);
        $item = $this->item->load('tags');

        $item->update([
            'embedding' => Embeddings::for([$item->embeddingText()])
                ->dimensions(config('pkm.embedding_dimensions'))
                ->generate()
                ->first(),
        ]);

        $candidates = $this->candidates($item);
        $reasons = $candidates->isEmpty() ? [] : (new LinkJudge)->judge($item, $candidates);

        DB::transaction(function () use ($item, $candidates, $reasons) {
            $this->forgetSuggestions($item);
            $this->storeLinks($item, $candidates, $reasons);
        });
    }

    public function failed(?Throwable $exception): void
    {
        Log::warning("Linking item {$this->item->id} failed: {$exception?->getMessage()}");
    }

    /**
     * Similar items that the author has not already confirmed or rejected.
     *
     * @return Collection<int, Item>
     */
    private function candidates(Item $item): Collection
    {
        $reviewedIds = ItemLink::query()
            ->where('item_id', $item->id)
            ->where('state', '!=', LinkState::Suggested)
            ->pluck('related_item_id');

        return Item::query()
            ->select(['id', 'title', 'summary', 'raw'])
            ->selectVectorDistance('embedding', $item->embedding, as: 'distance')
            ->whereKeyNot($item->id)
            ->whereNotIn('id', $reviewedIds)
            ->whereNotNull('embedding')
            ->whereVectorSimilarTo('embedding', $item->embedding, minSimilarity: config('pkm.link_candidate_min_similarity'))
            ->limit(config('pkm.link_candidate_limit'))
            ->get();
    }

    private function forgetSuggestions(Item $item): void
    {
        ItemLink::query()->involving($item)->where('state', LinkState::Suggested)->delete();
    }

    /**
     * Store the approved links in both directions, most similar first.
     *
     * @param  Collection<int, Item>  $candidates
     * @param  array<int, string>  $reasons
     */
    private function storeLinks(Item $item, Collection $candidates, array $reasons): void
    {
        ItemLink::storeBothWays($item, $candidates
            ->filter(fn (Item $candidate) => array_key_exists($candidate->id, $reasons))
            ->take(config('pkm.link_limit'))
            ->mapWithKeys(fn (Item $candidate) => [$candidate->id => [
                'similarity' => round(1 - $candidate->distance, 4),
                'reason' => $reasons[$candidate->id] ?: null,
                'state' => LinkState::Suggested,
            ]])
            ->all());
    }
}
