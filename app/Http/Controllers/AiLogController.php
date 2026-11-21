<?php

namespace App\Http\Controllers;

use App\Models\AiRequest;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class AiLogController extends Controller
{
    /**
     * Number of days the totals cover.
     */
    private const PERIOD_DAYS = 30;

    /**
     * AI usage: totals, per purpose and per day.
     */
    public function stats(): View
    {
        $since = today()->subDays(self::PERIOD_DAYS - 1);
        $period = AiRequest::query()->where('created_at', '>=', $since);

        $totals = (clone $period)->toBase()->selectRaw(
            'count(*) as calls, coalesce(sum(input_tokens), 0) as input_tokens, coalesce(sum(output_tokens), 0) as output_tokens,
             count(error) as failures'
        )->first();

        $perPurpose = (clone $period)->toBase()
            ->select('name')
            ->selectRaw('count(*) as calls, sum(input_tokens) as input_tokens, sum(output_tokens) as output_tokens, count(error) as failures, round(avg(duration_ms)) as avg_duration_ms')
            ->groupBy('name')
            ->orderByDesc('calls')
            ->get();

        $perDay = AiRequest::query()->toBase()
            ->where('created_at', '>=', today()->subDays(13))
            ->selectRaw('date(created_at) as day, count(*) as calls, sum(input_tokens + output_tokens) as tokens, count(error) as failures')
            ->groupBy(DB::raw('date(created_at)'))
            ->orderByDesc('day')
            ->get();

        return view('ai-log.stats', [
            'periodDays' => self::PERIOD_DAYS,
            'totals' => $totals,
            'perPurpose' => $perPurpose,
            'perDay' => $perDay,
        ]);
    }

    /**
     * The individual AI calls, newest first, optionally filtered by purpose or failures.
     */
    public function index(Request $request): View
    {
        $requests = AiRequest::query()
            ->with('item:id,title,raw')
            ->when($request->query('name'), fn (Builder $query, string $name) => $query->where('name', $name))
            ->when($request->boolean('failed'), fn (Builder $query) => $query->whereNotNull('error'))
            ->latest('id')
            ->paginate(50)
            ->withQueryString();

        return view('ai-log.index', [
            'periodDays' => self::PERIOD_DAYS,
            'names' => AiRequest::query()->distinct()->orderBy('name')->pluck('name'),
            'requests' => $requests,
        ]);
    }
}
