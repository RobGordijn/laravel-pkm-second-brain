<?php

namespace App\Http\Controllers;

use App\Models\Project;
use Illuminate\Http\RedirectResponse;

class ProjectArchiveController extends Controller
{
    /**
     * Finish a project or drop an area: it moves to the archive together with its notes.
     */
    public function store(Project $project): RedirectResponse
    {
        $project->archive();

        return to_route('projects.index')->with('status', "“{$project->name}” is archived, along with the notes that belong nowhere else.");
    }

    /**
     * Bring a project back, including the notes that were archived with it.
     */
    public function destroy(Project $project): RedirectResponse
    {
        $project->restore();

        return to_route('projects.show', $project)->with('status', 'Active again.');
    }
}
