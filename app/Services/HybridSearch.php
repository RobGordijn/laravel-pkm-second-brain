<?php

namespace App\Services;

use App\Models\Item;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Log;
use Laravel\Ai\Embeddings;
use Throwable;

/**
 * Combines Postgres full-text search with pgvector similarity using Reciprocal Rank Fusion.
 */
class HybridSearch
{
    /**
     * Rank constant for Reciprocal Rank Fusion; higher values flatten the influence of top ranks.
     */
    private const RRF_K = 60;

    private const CANDIDATES_PER_METHOD = 50;

    /**
     * Search items by keywords and meaning, optionally narrowed by the given base query.
     *
     * @param  Builder<Item>  $filters
     * @return Collection<int, Item>
     */
    public function search(string $terms, Builder $filters, int $limit = 50): Collection
    {
        $scores = [];

        foreach ([$this->fullTextIds($terms, clone $filters), $this->semanticIds($terms, clone $filters)] as $rankedIds) {
            foreach ($rankedIds as $rank => $id) {
                $scores[$id] = ($scores[$id] ?? 0) + 1 / (self::RRF_K + $rank + 1);
            }
        }

        arsort($scores);
        $ids = array_slice(array_keys($scores), 0, $limit);

        return (clone $filters)
            ->whereKey($ids)
            ->get()
            ->sortBy(fn (Item $item) => array_search($item->id, $ids, true))
            ->values();
    }

    /**
     * @param  Builder<Item>  $query
     * @return list<int>
     */
    private function fullTextIds(string $terms, Builder $query): array
    {
        return $query
            ->fullTextSearch($terms)
            ->orderByRaw("ts_rank(search_vector, websearch_to_tsquery('simple', ?)) desc", [$terms])
            ->limit(self::CANDIDATES_PER_METHOD)
            ->pluck('id')
            ->all();
    }

    /**
     * Semantic matches; falls back to no results when embeddings are unavailable.
     *
     * @param  Builder<Item>  $query
     * @return list<int>
     */
    private function semanticIds(string $terms, Builder $query): array
    {
        try {
            $embedding = Embeddings::for([$terms])
                ->dimensions(config('pkm.embedding_dimensions'))
                ->cache()
                ->generate()
                ->first();
        } catch (Throwable $exception) {
            Log::warning('Semantic search unavailable: '.$exception->getMessage());

            return [];
        }

        return $query
            ->whereNotNull('embedding')
            ->whereVectorSimilarTo('embedding', $embedding, minSimilarity: config('pkm.search_min_similarity'))
            ->limit(self::CANDIDATES_PER_METHOD)
            ->pluck('id')
            ->all();
    }
}
