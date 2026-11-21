<?php

namespace App\Enums;

/**
 * Who attached a tag or project to a note: the AI pipeline, or the author by hand.
 * AI choices are replaced on reprocessing; the author's choices are kept.
 */
enum AssignmentSource: string
{
    case Ai = 'ai';
    case User = 'user';
}
