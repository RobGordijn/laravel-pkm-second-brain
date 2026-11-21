<?php

namespace App\Jobs;

use App\Ai\Agents\ItemAnalyzer;
use App\Enums\AssignmentSource;
use App\Enums\ItemStatus;
use App\Enums\ItemType;
use App\Listeners\LogAiRequests;
use App\Models\Item;
use App\Models\Project;
use App\Models\Tag;
use App\Services\WebPageReader;
use Illuminate\Contracts\Queue\ShouldBeUniqueUntilProcessing;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Laravel\Ai\Classification;
use Laravel\Ai\Classification\Boolean;
use Laravel\Ai\Classification\Choice;
use Laravel\Ai\Responses\Data\BooleanAnswer;
use Laravel\Ai\Responses\Data\ChoiceAnswer;
use Throwable;

/**
 * Runs a captured item through the AI pipeline: read the linked page, classify and structure,
 * then hand over to LinkItem for embedding and linking.
 * A note the author wrote keeps its text, title and type; the AI only adds summary, tags, actions and project.
 * Unique only while queued, so a retry or clarification during a run is not silently dropped.
 */
class ProcessItem implements ShouldBeUniqueUntilProcessing, ShouldQueue
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

    public function handle(WebPageReader $reader): void
    {
        LogAiRequests::forItem($this->item->id);
        $item = $this->item;
        $item->update(['status' => ItemStatus::Processing, 'error' => null]);
        $this->readSource($item, $reader);

        $classification = $this->classify($item);
        $activeProjects = Project::query()->active()->orderBy('name')->get();
        $analysis = (new ItemAnalyzer($this->existingTagNames(), activeProjects: $this->describeProjects($activeProjects)))
            ->prompt($item->promptText());

        DB::transaction(function () use ($item, $classification, $analysis, $activeProjects) {
            $isVague = $classification['vague']->isTrue(config('pkm.vague_threshold'));
            $alreadyClarified = filled($item->clarification_answer);
            $question = trim((string) $analysis['clarification_question']);
            $asksForFeedback = ! $item->isAuthored() && $isVague && ! $alreadyClarified && $question !== '';
            $keepsType = $item->isAuthored() && $item->type !== null;

            $item->fill([
                'type' => $keepsType ? $item->type : $classification['type']->choice,
                'type_confidence' => $keepsType ? $item->type_confidence : $classification['type']->probabilityOf($classification['type']->choice),
                'title' => $item->isAuthored() && filled($item->title) ? $item->title : $analysis['title'],
                'summary' => $analysis['summary'],
                'body' => $item->isAuthored() ? $item->body : $analysis['body'],
                'status' => $asksForFeedback ? ItemStatus::NeedsFeedback : ItemStatus::Ready,
                'clarification_question' => $asksForFeedback ? $question : $item->clarification_question,
                'processed_at' => now(),
            ])->save();

            $this->syncTags($item, $analysis['tags']);
            $this->syncActions($item, $analysis['actions']);
            $this->syncProject($item, (string) ($analysis['project'] ?? ''), $activeProjects);
        });

        LinkItem::dispatch($item);
    }

    public function failed(?Throwable $exception): void
    {
        $this->item->update([
            'status' => ItemStatus::Failed,
            'error' => $exception?->getMessage(),
        ]);
    }

    /**
     * @return array{type: ChoiceAnswer, vague: BooleanAnswer}
     */
    private function classify(Item $item): array
    {
        $result = Classification::of($item->promptText())
            ->questions([
                'type' => new Choice(
                    'What kind of entry is this personal note?',
                    collect(ItemType::classifiable())->mapWithKeys(fn (ItemType $type) => [$type->value => $type->description()])->all(),
                ),
                'vague' => new Boolean('Is this note too vague to understand later without extra context?', [
                    'true' => 'Unclear what it refers to, why it was saved or what to do with it',
                    'false' => 'Self-explanatory, or a URL/quote that speaks for itself',
                ]),
            ])
            ->classify();

        return ['type' => $result['type'], 'vague' => $result['vague']];
    }

    /**
     * @return list<string>
     */
    private function existingTagNames(): array
    {
        return Tag::query()
            ->withCount('items')
            ->orderByDesc('items_count')
            ->limit(200)
            ->pluck('name')
            ->all();
    }

    /**
     * Replace the AI tags while keeping tags the author added by hand.
     *
     * @param  list<string>  $names
     */
    private function syncTags(Item $item, array $names): void
    {
        $item->tags()->wherePivot('source', AssignmentSource::Ai)->detach();

        $tagIds = collect($names)
            ->filter(fn (string $name) => trim($name) !== '')
            ->map(fn (string $name) => Tag::findOrCreateByName($name)->id)
            ->unique()
            ->diff($item->tags()->pluck('tags.id'))
            ->mapWithKeys(fn (int $id) => [$id => ['source' => AssignmentSource::Ai]]);

        $item->tags()->attach($tagIds->all());
    }

    /**
     * @param  Collection<int, Project>  $projects
     * @return list<array{name: string, kind: string, goal: ?string}>
     */
    private function describeProjects(Collection $projects): array
    {
        return $projects
            ->map(fn (Project $project) => ['name' => $project->name, 'kind' => $project->kind->value, 'goal' => $project->goal])
            ->values()
            ->all();
    }

    /**
     * Replace the AI's project choice, unless the author already placed the note by hand.
     *
     * @param  Collection<int, Project>  $activeProjects
     */
    private function syncProject(Item $item, string $name, Collection $activeProjects): void
    {
        $item->projects()->wherePivot('source', AssignmentSource::Ai)->detach();

        if ($item->projects()->exists()) {
            return;
        }

        $project = $activeProjects->first(fn (Project $project) => Str::lower($project->name) === Str::lower(Str::squish($name)));

        if ($project) {
            $item->projects()->attach($project->id, ['source' => AssignmentSource::Ai]);
        }
    }

    /**
     * Replace open actions; actions that are already done are kept.
     *
     * @param  list<array{text: string, due_on: ?string}>  $actions
     */
    private function syncActions(Item $item, array $actions): void
    {
        $item->actions()->whereNull('done_at')->delete();

        foreach ($actions as $action) {
            $item->actions()->create([
                'text' => $action['text'],
                'due_on' => $this->parseDate($action['due_on'] ?? null),
            ]);
        }
    }

    private function parseDate(?string $date): ?string
    {
        if ($date === null || ! preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            return null;
        }

        return $date;
    }

    /**
     * Fetch the linked web page once, so the AI can summarise the page instead of just the URL.
     */
    private function readSource(Item $item, WebPageReader $reader): void
    {
        if ($item->url === null || $item->source_fetched_at !== null) {
            return;
        }

        try {
            $page = $reader->read($item->url);
        } catch (Throwable $exception) {
            Log::info("Could not read {$item->url}: {$exception->getMessage()}");
            $page = null;
        }

        $item->update([
            'source_title' => $page['title'] ?? null,
            'source_text' => $page['text'] ?? null,
            'source_fetched_at' => now(),
        ]);
    }
}
