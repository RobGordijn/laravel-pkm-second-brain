<?php

namespace App\Jobs;

use App\Ai\Agents\DigestWriter;
use App\Listeners\LogAiRequests;
use App\Models\Digest;
use App\Models\Item;
use App\Models\ItemAction;
use App\Models\Project;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Str;

/**
 * Builds the periodic overview: what was captured, what is still open and what belongs together.
 * Unique, so clicking the button twice does not produce two overviews.
 */
class GenerateDigest implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries = 2;

    public int $backoff = 60;

    /**
     * Seconds after which the unique lock is released, should a run never finish.
     */
    public int $uniqueFor = 600;

    public function __construct(public int $days = 7) {}

    public function handle(): Digest
    {
        LogAiRequests::forItem(null);

        $periodStart = today()->subDays($this->days - 1);
        $periodEnd = today();

        $newItems = Item::query()
            ->with(['tags', 'projects'])
            ->where('created_at', '>=', $periodStart)
            ->oldest()
            ->get();

        $openActions = ItemAction::query()
            ->with('item')
            ->open()
            ->orderByRaw('due_on is null')
            ->orderBy('due_on')
            ->limit(50)
            ->get();

        $waitingItems = Item::query()->needsFeedback()->latest()->limit(20)->get();

        $stats = [
            'new_items' => $newItems->count(),
            'open_actions' => ItemAction::query()->open()->count(),
            'overdue_actions' => ItemAction::query()->open()->where('due_on', '<', today())->count(),
            'needs_feedback' => Item::query()->needsFeedback()->count(),
        ];

        if ($newItems->isEmpty() && $openActions->isEmpty() && $waitingItems->isEmpty()) {
            $content = 'A quiet period: nothing new was captured and there are no open actions.';
            $suggestions = [];
        } else {
            $activeProjects = Project::query()->active()->orderBy('name')->get();
            $response = (new DigestWriter)->write($periodStart, $periodEnd, $newItems, $openActions, $waitingItems, $activeProjects);
            $content = $response['content'];
            $shownIds = array_merge($newItems->modelKeys(), $openActions->pluck('item_id')->all(), $waitingItems->modelKeys());
            $suggestions = $this->projectSuggestions($response['project_suggestions'] ?? [], $shownIds);
        }

        return Digest::create([
            'period_start' => $periodStart,
            'period_end' => $periodEnd,
            'content' => trim($content),
            'stats' => $stats,
            'project_suggestions' => $suggestions,
        ]);
    }

    /**
     * Keep only groups that refer to notes the AI was actually shown, with at least two of them.
     *
     * @param  array<int, array{name?: string, kind?: string, goal?: string, item_ids?: array<int, int|string>}>  $groups
     * @param  list<int>  $knownIds
     * @return list<array{name: string, kind: string, goal: string, item_ids: list<int>}>
     */
    private function projectSuggestions(array $groups, array $knownIds): array
    {
        return collect($groups)
            ->map(fn (array $group) => [
                'name' => Str::squish((string) ($group['name'] ?? '')),
                'kind' => ($group['kind'] ?? null) === 'area' ? 'area' : 'project',
                'goal' => trim((string) ($group['goal'] ?? '')),
                'item_ids' => collect($group['item_ids'] ?? [])->map(fn ($id) => (int) $id)->intersect($knownIds)->unique()->values()->all(),
            ])
            ->filter(fn (array $group) => $group['name'] !== '' && count($group['item_ids']) >= 2)
            ->take(3)
            ->values()
            ->all();
    }
}
