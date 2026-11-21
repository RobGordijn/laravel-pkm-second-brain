<?php

namespace App\Enums;

/**
 * Where a link between two notes stands: proposed by the AI, or judged by the author.
 */
enum LinkState: string
{
    case Suggested = 'suggested';
    case Confirmed = 'confirmed';
    case Rejected = 'rejected';
}
