<?php

namespace App\Http\Controllers;

use App\Http\Requests\MergeTagsRequest;
use App\Models\Tag;
use Illuminate\Http\RedirectResponse;

class TagMergeController extends Controller
{
    /**
     * Merge one or more tags into a target tag.
     */
    public function store(MergeTagsRequest $request): RedirectResponse
    {
        $target = Tag::findOrFail($request->validated('target_id'));
        $sources = Tag::query()->whereKey($request->validated('source_ids'))->whereKeyNot($target->id)->get();

        $sources->each(fn (Tag $source) => $source->mergeInto($target));

        $request->session()->forget('tag_suggestions');

        return to_route('tags.index')->with(
            'status',
            $sources->pluck('name')->map(fn (string $name) => "#{$name}")->implode(', ')." merged into #{$target->name}.",
        );
    }
}
