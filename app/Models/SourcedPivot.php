<?php

namespace App\Models;

use App\Enums\AssignmentSource;
use Illuminate\Database\Eloquent\Relations\Pivot;

/**
 * Pivot row of item_tag or item_project, recording who made the assignment.
 *
 * @property AssignmentSource $source
 */
class SourcedPivot extends Pivot
{
    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'source' => AssignmentSource::class,
        ];
    }

    public function isFromAi(): bool
    {
        return $this->source === AssignmentSource::Ai;
    }
}
