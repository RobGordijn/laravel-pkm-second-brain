<?php

namespace App\Http\Controllers;

use App\Enums\AssignmentSource;
use App\Enums\ItemStatus;
use App\Enums\ItemType;
use App\Enums\LinkState;
use App\Http\Requests\SaveAnswerRequest;
use App\Jobs\LinkItem;
use App\Models\Item;
use App\Models\ItemLink;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class SaveAnswerController extends Controller
{
    /**
     * Keep an answer as a note of its own (an intermediate packet), linked to the notes it was built from.
     * It inherits their tags and active projects, so it shows up where they do.
     */
    public function __invoke(SaveAnswerRequest $request): RedirectResponse
    {
        $question = Str::squish($request->validated('question'));
        $answer = trim($request->validated('answer'));
        $sources = Item::query()->whereKey($request->sourceIds())->with(['tags', 'projects' => fn ($projects) => $projects->active()])->get();

        $item = DB::transaction(function () use ($question, $answer, $sources) {
            $item = Item::create([
                'raw' => "Question: {$question}\n\n{$answer}",
                'type' => ItemType::Synthesis,
                'type_confidence' => 1.0,
                'title' => Str::limit($question, 120),
                'summary' => $this->summarize($answer),
                'body' => $answer,
                'status' => ItemStatus::Ready,
                'processed_at' => now(),
                'reviewed_at' => now(),
            ]);

            $item->tags()->attach($sources->flatMap->tags->pluck('id')->unique()->mapWithKeys(fn (int $id) => [$id => ['source' => AssignmentSource::Ai]])->all());
            $item->projects()->attach($sources->flatMap->projects->pluck('id')->unique()->mapWithKeys(fn (int $id) => [$id => ['source' => AssignmentSource::Ai]])->all());
            $this->linkSources($item, $sources->modelKeys());

            return $item;
        });

        LinkItem::dispatch($item);

        return to_route('items.show', $item)->with('status', 'Answer saved as a synthesis, linked to '.$sources->count().' '.($sources->count() === 1 ? 'source' : 'sources').'.');
    }

    /**
     * The first sentences of the answer as plain text, without note references.
     */
    private function summarize(string $answer): string
    {
        $plain = Str::of($answer)
            ->replaceMatches('/\[#\d+(?:\s*,\s*#?\d+)*\]/', '')
            ->replaceMatches('/[*_`#>]|^\s*[-+]\s+/m', '')
            ->squish()
            ->replaceMatches('/\s+([.,;:!?])/', '$1');

        return Str::limit($plain->toString(), 200);
    }

    /**
     * Confirmed links in both directions, so LinkItem keeps them and the sources point to the new note.
     *
     * @param  list<int>  $sourceIds
     */
    private function linkSources(Item $item, array $sourceIds): void
    {
        ItemLink::storeBothWays($item, collect($sourceIds)->mapWithKeys(fn (int $sourceId) => [$sourceId => [
            'similarity' => 1.0,
            'reason' => 'Source of this synthesis',
            'reverse_reason' => 'Used in this synthesis',
            'state' => LinkState::Confirmed,
        ]])->all());
    }
}
