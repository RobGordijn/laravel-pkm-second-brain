<?php

namespace App\Models;

use App\Ai\Agents\LabelledAgent;
use Database\Factories\AiRequestFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\MassPrunable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One call to an AI provider (agent prompt, embeddings or classification), with its token usage.
 */
#[Fillable([
    'invocation_id', 'kind', 'name', 'provider', 'model', 'item_id', 'input_tokens', 'output_tokens',
    'cached_input_tokens', 'reasoning_tokens', 'duration_ms', 'request', 'response', 'error', 'created_at',
])]
class AiRequest extends Model
{
    /** @use HasFactory<AiRequestFactory> */
    use HasFactory, MassPrunable;

    public const UPDATED_AT = null;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'input_tokens' => 'integer',
            'output_tokens' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<Item, $this>
     */
    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }

    /**
     * Human-readable name of what the call was for: the agent's own label,
     * or a fixed name for embeddings and classification calls.
     */
    public static function labelFor(string $name): string
    {
        $agent = 'App\\Ai\\Agents\\'.$name;

        if (preg_match('/^[A-Za-z]+$/', $name) && is_a($agent, LabelledAgent::class, true)) {
            return $agent::label();
        }

        return match ($name) {
            'Classification' => 'Classification',
            default => $name,
        };
    }

    public function label(): string
    {
        return static::labelFor($this->name);
    }

    public function failed(): bool
    {
        return $this->error !== null;
    }

    /**
     * Get the prunable model query.
     *
     * @return Builder<AiRequest>
     */
    public function prunable(): Builder
    {
        return static::query()->where('created_at', '<', now()->subDays(config('pkm.ai_log_retention_days')));
    }
}
