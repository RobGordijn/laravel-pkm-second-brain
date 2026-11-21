<?php

namespace App\Ai\Agents;

use App\Models\Tag;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Database\Eloquent\Collection;
use Laravel\Ai\Attributes\Temperature;
use Laravel\Ai\Attributes\Timeout;
use Laravel\Ai\Attributes\UseCheapestModel;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\HasStructuredOutput;
use Laravel\Ai\Promptable;
use Stringable;

/**
 * Spots duplicate tags (synonyms, spelling variants, singular/plural) and proposes merges.
 */
#[UseCheapestModel]
#[Temperature(0.1)]
#[Timeout(60)]
class TagCurator implements Agent, HasStructuredOutput, LabelledAgent
{
    use Promptable;

    public static function label(): string
    {
        return 'Tidy tags';
    }

    /**
     * Get the instructions that the agent should follow.
     */
    public function instructions(): Stringable|string
    {
        return <<<'PROMPT'
            You keep the tags of a personal knowledge base tidy. You get all tags with their usage count.
            Find groups of tags that mean the same thing: synonyms, spelling variants, singular/plural,
            translations or abbreviations (e.g. "health" + "healthcare", "film" + "films").

            For each group choose the target: the clearest and most used tag. List the other tags as sources.
            Give a short reason, in the language most tags are in. Do NOT merge tags that are merely related (e.g. "laravel" and "php"
            are different topics). Only use tag names exactly as given. Return an empty list when nothing
            should be merged.
            PROMPT;
    }

    /**
     * Proposed merges that refer to existing tags only, translated to ids.
     *
     * @param  Collection<int, Tag>  $tags  With items_count loaded.
     * @return list<array{target_id: int, source_ids: list<int>, reason: string}>
     */
    public function proposeMerges(Collection $tags): array
    {
        $response = $this->prompt($tags->map(fn (Tag $tag) => "{$tag->name} ({$tag->items_count})")->implode("\n"));
        $idsByName = $tags->pluck('id', 'name');

        return collect($response['merges'])
            ->map(function (array $merge) use ($idsByName) {
                $targetId = $idsByName[$merge['target']] ?? null;
                $sourceIds = collect($merge['sources'])
                    ->map(fn (string $name) => $idsByName[$name] ?? null)
                    ->filter(fn (?int $id) => $id !== null && $id !== $targetId)
                    ->unique()
                    ->values()
                    ->all();

                return $targetId && $sourceIds !== []
                    ? ['target_id' => $targetId, 'source_ids' => $sourceIds, 'reason' => $merge['reason']]
                    : null;
            })
            ->filter()
            ->values()
            ->all();
    }

    /**
     * Get the agent's structured output schema definition.
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'merges' => $schema->array()->items(
                $schema->object(fn (JsonSchema $schema) => [
                    'target' => $schema->string()->required(),
                    'sources' => $schema->array()->items($schema->string())->required(),
                    'reason' => $schema->string()->required(),
                ])
            )->required(),
        ];
    }
}
