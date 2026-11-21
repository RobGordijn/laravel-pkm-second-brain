<?php

namespace App\Http\Controllers;

use App\Enums\AssignmentSource;
use App\Enums\ItemStatus;
use App\Enums\ItemType;
use App\Http\Requests\StoreItemRequest;
use App\Http\Requests\UpdateItemRequest;
use App\Jobs\LinkItem;
use App\Models\Item;
use App\Models\Project;
use App\Models\Tag;
use App\Services\HybridSearch;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class ItemController extends Controller
{
    /**
     * The inbox: latest items, or hybrid search results when a query is given.
     * Archived notes only show up when the archive filter is on.
     */
    public function index(Request $request, HybridSearch $search): View|Response
    {
        $filters = $this->filteredQuery($request)->with(['tags', 'actions', 'projects']);
        $terms = trim((string) $request->query('q'));

        $items = $terms === ''
            ? $filters->latest()->limit(100)->get()
            : $search->search($terms, $filters);

        if ($request->header('X-Fragment')) {
            return response()->view('items._list', ['items' => $items, 'terms' => $terms]);
        }

        return view('items.index', [
            'items' => $items,
            'terms' => $terms,
            'tags' => Tag::query()->has('items')->orderBy('name')->get(),
            'projects' => Project::query()->active()->orderBy('kind')->orderBy('name')->get(),
            'types' => ItemType::cases(),
            'feedbackCount' => Item::query()->needsFeedback()->count(),
        ]);
    }

    public function store(StoreItemRequest $request): JsonResponse|RedirectResponse
    {
        $item = Item::capture($request->validated('raw'), $request->validated('why_saved'));

        if ($request->expectsJson()) {
            return response()->json([
                'id' => $item->id,
                'html' => view('items._card', ['item' => $item->load(['tags', 'actions', 'projects'])])->render(),
            ], 201);
        }

        return to_route('items.index');
    }

    public function show(Request $request, Item $item): View|JsonResponse
    {
        $item->load([
            'tags', 'actions', 'projects', 'relatedItems',
            'mentionedItems' => fn (BelongsToMany $query) => $query->orderBy('title'),
            'mentionedBy' => fn (BelongsToMany $query) => $query->orderBy('title'),
        ]);

        if ($request->expectsJson()) {
            return response()->json([
                'id' => $item->id,
                'status' => $item->status->value,
                'in_progress' => $item->status->isInProgress(),
                'html' => view('items._card', ['item' => $item])->render(),
            ]);
        }

        $isReturnAfterOwnAction = $request->session()->has('status') || $request->session()->hasOldInput() || $request->session()->has('errors');

        if (! $isReturnAfterOwnAction) {
            $item->recordUse();
        }

        return view('items.show', ['item' => $item]);
    }

    public function update(UpdateItemRequest $request, Item $item): RedirectResponse
    {
        DB::transaction(function () use ($request, $item) {
            $item->update([
                ...$request->safe()->only(['title', 'summary', 'body', 'type', 'why_saved']),
                'is_authored' => $item->isAuthored() || ($request->has('body') && (string) $request->validated('body') !== (string) $item->body),
                'reviewed_at' => $item->reviewed_at ?? now(),
            ]);

            $item->tags()->sync(
                collect($request->tagNames())
                    ->mapWithKeys(fn (string $name) => [Tag::findOrCreateByName($name)->id => ['source' => AssignmentSource::User]])
                    ->all()
            );

            $item->projects()->sync(
                collect($request->validated('projects', []))
                    ->mapWithKeys(fn (int|string $id) => [(int) $id => ['source' => AssignmentSource::User]])
                    ->all()
            );
        });

        LinkItem::dispatch($item);

        return to_route('items.show', $item)->with('status', 'Saved. Links are being recalculated.');
    }

    public function destroy(Item $item): RedirectResponse
    {
        $item->delete();

        return to_route('items.index')->with('status', 'Deleted.');
    }

    /**
     * @return Builder<Item>
     */
    private function filteredQuery(Request $request): Builder
    {
        return Item::query()
            ->when(
                $request->boolean('archived'),
                fn (Builder $query) => $query->whereNotNull('archived_at'),
                fn (Builder $query) => $query->unarchived(),
            )
            ->when($request->query('project'), fn (Builder $query, string $slug) => $query->whereHas('projects', fn (Builder $projects) => $projects->where('slug', $slug)))
            ->when($request->boolean('unfiled'), fn (Builder $query) => $query->whereDoesntHave('projects'))
            ->when($request->boolean('distill'), fn (Builder $query) => $query->awaitingDistillation()->orderByDesc('use_count'))
            ->when($request->query('tag'), fn (Builder $query, string $slug) => $query->whereHas('tags', fn (Builder $tags) => $tags->where('slug', $slug)))
            ->when(ItemType::tryFrom((string) $request->query('type')), fn (Builder $query, ItemType $type) => $query->where('type', $type))
            ->when(ItemStatus::tryFrom((string) $request->query('status')), fn (Builder $query, ItemStatus $status) => $query->where('status', $status))
            ->when($request->boolean('open_actions'), fn (Builder $query) => $query->whereHas('actions', fn (Builder $actions) => $actions->open()));
    }
}
