<?php

use App\Http\Controllers\AiLogController;
use App\Http\Controllers\AskController;
use App\Http\Controllers\ClarificationController;
use App\Http\Controllers\DigestController;
use App\Http\Controllers\DigestProjectController;
use App\Http\Controllers\ExportController;
use App\Http\Controllers\HowItWorksController;
use App\Http\Controllers\ItemActionController;
use App\Http\Controllers\ItemArchiveController;
use App\Http\Controllers\ItemAuthorshipController;
use App\Http\Controllers\ItemController;
use App\Http\Controllers\ItemInsightController;
use App\Http\Controllers\ItemInsightSuggestionController;
use App\Http\Controllers\ItemLinkController;
use App\Http\Controllers\ItemLookupController;
use App\Http\Controllers\MarkdownPreviewController;
use App\Http\Controllers\ProjectArchiveController;
use App\Http\Controllers\ProjectController;
use App\Http\Controllers\RetryItemController;
use App\Http\Controllers\ReviewController;
use App\Http\Controllers\SaveAnswerController;
use App\Http\Controllers\TagController;
use App\Http\Controllers\TagMergeController;
use App\Http\Controllers\TagSuggestionController;
use App\Http\Controllers\TodayController;
use App\Http\Controllers\WriteItemController;
use Illuminate\Support\Facades\Route;

Route::get('/', [ItemController::class, 'index'])->name('items.index');
Route::get('/today', TodayController::class)->name('today');
Route::get('/review', [ReviewController::class, 'index'])->name('review.index');
Route::post('/review/{item}', [ReviewController::class, 'store'])->name('review.store');

Route::get('/items/lookup', ItemLookupController::class)->name('items.lookup');
Route::post('/items/preview', MarkdownPreviewController::class)->name('items.preview');
Route::get('/items/write', [WriteItemController::class, 'create'])->name('items.write');
Route::post('/items/write', [WriteItemController::class, 'store'])->name('items.write.store');
Route::get('/items/{item}/write', [WriteItemController::class, 'edit'])->name('items.write.edit');
Route::put('/items/{item}/write', [WriteItemController::class, 'update'])->name('items.write.update');
Route::resource('items', ItemController::class)->only(['store', 'show', 'update', 'destroy']);

Route::prefix('items/{item}')->name('items.')->group(function () {
    Route::post('retry', RetryItemController::class)->name('retry');
    Route::post('clarification', [ClarificationController::class, 'store'])->name('clarification');
    Route::patch('links/{related}', [ItemLinkController::class, 'update'])->name('links.update');
    Route::patch('insight', [ItemInsightController::class, 'update'])->name('insight.update');
    Route::post('insight/suggestion', ItemInsightSuggestionController::class)->name('insight.suggest');
    Route::post('archive', [ItemArchiveController::class, 'store'])->name('archive');
    Route::delete('archive', [ItemArchiveController::class, 'destroy'])->name('unarchive');
    Route::delete('authored', [ItemAuthorshipController::class, 'destroy'])->name('authored.destroy');
});

Route::resource('projects', ProjectController::class)->only(['index', 'store', 'show', 'update']);
Route::post('/projects/{project}/archive', [ProjectArchiveController::class, 'store'])->name('projects.archive');
Route::delete('/projects/{project}/archive', [ProjectArchiveController::class, 'destroy'])->name('projects.unarchive');

Route::patch('/actions/{action}', [ItemActionController::class, 'update'])->name('actions.update');

Route::get('/ask', [AskController::class, 'index'])->name('ask.index');
Route::post('/ask', [AskController::class, 'store'])->name('ask.store');
Route::post('/ask/save', SaveAnswerController::class)->name('ask.save');

Route::resource('digests', DigestController::class)->only(['index', 'store', 'show']);
Route::post('/digests/{digest}/projects/{suggestion}', [DigestProjectController::class, 'store'])->whereNumber('suggestion')->name('digests.projects.store');

Route::get('/tags', [TagController::class, 'index'])->name('tags.index');
Route::post('/tags/suggestions', [TagSuggestionController::class, 'store'])->name('tags.suggestions');
Route::post('/tags/merge', [TagMergeController::class, 'store'])->name('tags.merge');

Route::get('/export', [ExportController::class, 'show'])->name('export');
Route::get('/export/download', [ExportController::class, 'download'])->name('export.download');

Route::get('/ai-log', [AiLogController::class, 'index'])->name('ai-log.index');
Route::get('/ai-log/stats', [AiLogController::class, 'stats'])->name('ai-log.stats');
Route::get('/how-it-works', HowItWorksController::class)->name('how-it-works');
