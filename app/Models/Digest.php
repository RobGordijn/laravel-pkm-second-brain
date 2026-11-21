<?php

namespace App\Models;

use Database\Factories\DigestFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * A periodic AI-written overview of what was captured, what is open and what belongs together.
 *
 * @property array{new_items: int, open_actions: int, overdue_actions: int, needs_feedback: int} $stats
 * @property list<array{name: string, kind: string, goal: string, item_ids: list<int>, project_id?: int}>|null $project_suggestions
 */
#[Fillable(['period_start', 'period_end', 'content', 'stats', 'project_suggestions'])]
class Digest extends Model
{
    /** @use HasFactory<DigestFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'period_start' => 'date',
            'period_end' => 'date',
            'stats' => 'array',
            'project_suggestions' => 'array',
        ];
    }
}
