<?php

namespace App\Http\Controllers;

use App\Models\Item;
use App\Models\Project;
use App\Services\MarkdownExport;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ExportController extends Controller
{
    /**
     * What the export contains and how to use it, with the download button.
     */
    public function show(): View
    {
        return view('export', [
            'itemCount' => Item::query()->count(),
            'projectCount' => Project::query()->count(),
        ]);
    }

    /**
     * Download every note, project and area as a zip of markdown files.
     */
    public function download(MarkdownExport $export): BinaryFileResponse
    {
        return response()
            ->download($export->toZip(), 'brain-export-'.now()->toDateString().'.zip', ['Content-Type' => 'application/zip'])
            ->deleteFileAfterSend();
    }
}
