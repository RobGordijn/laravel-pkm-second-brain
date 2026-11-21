<?php

namespace App\Http\Controllers;

use App\View\Components\AiMarkdown;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Blade;

class MarkdownPreviewController extends Controller
{
    /**
     * Render a draft exactly as the note page will show it, wiki-links included.
     */
    public function __invoke(Request $request): Response
    {
        $validated = $request->validate(['body' => ['nullable', 'string', 'max:50000']]);

        return response(Blade::renderComponent(new AiMarkdown((string) ($validated['body'] ?? ''))));
    }
}
