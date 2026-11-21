<?php

namespace App\Listeners;

use App\Models\AiRequest;
use Illuminate\Events\Dispatcher;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Laravel\Ai\Events\AgentFailed;
use Laravel\Ai\Events\AgentPrompted;
use Laravel\Ai\Events\ClassificationFailed;
use Laravel\Ai\Events\Classified;
use Laravel\Ai\Events\Classifying;
use Laravel\Ai\Events\EmbeddingsFailed;
use Laravel\Ai\Events\EmbeddingsGenerated;
use Laravel\Ai\Events\GeneratingEmbeddings;
use Laravel\Ai\Events\PromptingAgent;
use Laravel\Ai\Responses\Data\TextUsage;
use Laravel\Ai\Responses\Data\Usage;
use Throwable;

/**
 * Records every AI call (agents, embeddings, classification) with tokens and duration.
 *
 * Registered as a singleton so start times can be matched to completion events by invocation id.
 */
class LogAiRequests
{
    /**
     * Context key holding the id of the item the current AI calls are about.
     */
    public const ITEM_CONTEXT_KEY = 'pkm_item_id';

    private const MAX_TEXT_LENGTH = 20_000;

    /**
     * @var array<string, float>
     */
    private array $startedAt = [];

    /**
     * Mark the item that AI calls in the current request or job belong to.
     */
    public static function forItem(?int $itemId): void
    {
        Context::addHidden(self::ITEM_CONTEXT_KEY, $itemId);
    }

    /**
     * @return array<class-string, string>
     */
    public function subscribe(Dispatcher $events): array
    {
        return [
            PromptingAgent::class => 'start',
            GeneratingEmbeddings::class => 'start',
            Classifying::class => 'start',
            AgentPrompted::class => 'agentPrompted',
            AgentFailed::class => 'agentFailed',
            EmbeddingsGenerated::class => 'embeddingsGenerated',
            EmbeddingsFailed::class => 'embeddingsFailed',
            Classified::class => 'classified',
            ClassificationFailed::class => 'classificationFailed',
        ];
    }

    public function start(PromptingAgent|GeneratingEmbeddings|Classifying $event): void
    {
        $this->startedAt[$event->invocationId] = microtime(true);
    }

    public function agentPrompted(AgentPrompted $event): void
    {
        $this->record($event->invocationId, [
            'kind' => 'agent',
            'name' => class_basename($event->prompt->agent),
            'provider' => $event->response->meta->provider ?? $event->prompt->provider->name(),
            'model' => $event->response->meta->model ?? $event->prompt->model,
            'request' => $event->prompt->prompt,
            'response' => $event->response->text,
        ], $event->response->usage);
    }

    public function agentFailed(AgentFailed $event): void
    {
        $this->record($event->invocationId, [
            'kind' => 'agent',
            'name' => class_basename($event->prompt->agent),
            'provider' => $event->prompt->provider->name(),
            'model' => $event->prompt->model,
            'request' => $event->prompt->prompt,
        ], exception: $event->exception);
    }

    public function embeddingsGenerated(EmbeddingsGenerated $event): void
    {
        $this->record($event->invocationId, [
            'kind' => 'embeddings',
            'name' => 'Embeddings',
            'provider' => $event->provider->name(),
            'model' => $event->model,
            'request' => implode("\n---\n", $event->prompt->inputs),
            'response' => count($event->response->embeddings)." vector(s) × {$event->prompt->dimensions} dimensions",
        ], $event->response->usage);
    }

    public function embeddingsFailed(EmbeddingsFailed $event): void
    {
        $this->record($event->invocationId, [
            'kind' => 'embeddings',
            'name' => 'Embeddings',
            'provider' => $event->provider->name(),
            'model' => $event->model,
            'request' => implode("\n---\n", $event->prompt->inputs),
        ], exception: $event->exception);
    }

    public function classified(Classified $event): void
    {
        $this->record($event->invocationId, [
            'kind' => 'classification',
            'name' => 'Classification',
            'provider' => $event->provider->name(),
            'model' => $event->model,
            'request' => $this->classificationRequest($event->prompt->state, $event->prompt->questions),
            'response' => json_encode($event->response->toArray(), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE),
        ], $event->response->usage);
    }

    public function classificationFailed(ClassificationFailed $event): void
    {
        $this->record($event->invocationId, [
            'kind' => 'classification',
            'name' => 'Classification',
            'provider' => $event->provider->name(),
            'model' => $event->model,
            'request' => $this->classificationRequest($event->prompt->state, $event->prompt->questions),
        ], exception: $event->exception);
    }

    /**
     * @param  array{kind: string, name: string, provider: ?string, model: ?string, request: string, response?: ?string}  $attributes
     */
    private function record(string $invocationId, array $attributes, ?Usage $usage = null, ?Throwable $exception = null): void
    {
        $startedAt = $this->startedAt[$invocationId] ?? null;
        unset($this->startedAt[$invocationId]);

        $inputTokens = $usage?->inputTokens ?? 0;
        $outputTokens = $usage?->outputTokens ?? 0;

        try {
            AiRequest::create([
                ...$attributes,
                'invocation_id' => $invocationId,
                'item_id' => Context::getHidden(self::ITEM_CONTEXT_KEY),
                'input_tokens' => $inputTokens,
                'output_tokens' => $outputTokens,
                'cached_input_tokens' => $usage instanceof TextUsage ? $usage->cacheReadInputTokens : null,
                'reasoning_tokens' => $usage instanceof TextUsage ? $usage->reasoningTokens : null,
                'duration_ms' => $startedAt ? (int) round((microtime(true) - $startedAt) * 1000) : null,
                'request' => Str::limit($attributes['request'], self::MAX_TEXT_LENGTH),
                'response' => isset($attributes['response']) ? Str::limit($attributes['response'], self::MAX_TEXT_LENGTH) : null,
                'error' => $exception ? Str::limit(class_basename($exception).': '.$exception->getMessage(), 2000) : null,
            ]);
        } catch (Throwable $loggingError) {
            Log::warning('Could not log AI request: '.$loggingError->getMessage());
        }
    }

    /**
     * @param  string|array<mixed>  $state
     * @param  array<string, mixed>  $questions
     */
    private function classificationRequest(string|array $state, array $questions): string
    {
        $questionList = collect($questions)
            ->map(fn ($question, string $key) => "{$key}: ".(is_object($question) && method_exists($question, 'toArray')
                ? json_encode($question->toArray(), JSON_UNESCAPED_UNICODE)
                : json_encode($question, JSON_UNESCAPED_UNICODE)))
            ->implode("\n");

        return (is_string($state) ? $state : json_encode($state, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE))
            ."\n\n---\nQuestions:\n".$questionList;
    }
}
