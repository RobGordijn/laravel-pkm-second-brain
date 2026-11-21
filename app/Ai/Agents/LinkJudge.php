<?php

namespace App\Ai\Agents;

use App\Models\Item;
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
 * Decides which semantically similar notes are genuinely related, and explains why.
 */
#[UseCheapestModel]
#[Temperature(0.1)]
#[Timeout(60)]
class LinkJudge implements Agent, HasStructuredOutput, LabelledAgent
{
    use Promptable;

    public static function label(): string
    {
        return 'Link judge';
    }

    /**
     * Get the instructions that the agent should follow.
     */
    public function instructions(): Stringable|string
    {
        return <<<'PROMPT'
            You maintain links between notes in a personal knowledge base. You get one note and a
            list of candidate notes that a vector search found. Decide for every candidate whether
            it is genuinely related: same project, person, object, topic or goal, so that the author
            would want to jump from one to the other.

            Not related: notes that merely share a format or mood, such as two unrelated to-dos,
            two appointments or two links. Be strict; a missing link is better than a wrong one.

            For related candidates write a reason: one short sentence in the language of the notes
            naming what connects them, e.g. "Both are about the attic renovation."
            For unrelated candidates the reason is an empty string.
            Return every candidate id exactly once.
            PROMPT;
    }

    /**
     * Ask which candidates are genuinely related to the note.
     *
     * @param  Collection<int, Item>  $candidates
     * @return array<int, string> Reasons keyed by related item id.
     */
    public function judge(Item $item, Collection $candidates): array
    {
        $prompt = "NOTE\n".$this->describe($item)."\n\nCANDIDATES\n".$candidates
            ->map(fn (Item $candidate) => "[id {$candidate->id}]\n".$this->describe($candidate))
            ->implode("\n\n");

        return collect($this->prompt($prompt)['candidates'])
            ->filter(fn (array $verdict) => $verdict['related'] && $candidates->contains('id', $verdict['id']))
            ->mapWithKeys(fn (array $verdict) => [(int) $verdict['id'] => trim($verdict['reason'])])
            ->all();
    }

    private function describe(Item $item): string
    {
        return trim(($item->title ?? '')."\n".($item->summary ?? str($item->raw)->limit(500)));
    }

    /**
     * Get the agent's structured output schema definition.
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'candidates' => $schema->array()->items(
                $schema->object(fn (JsonSchema $schema) => [
                    'id' => $schema->integer()->required(),
                    'related' => $schema->boolean()->required(),
                    'reason' => $schema->string()->required(),
                ])
            )->required(),
        ];
    }
}
