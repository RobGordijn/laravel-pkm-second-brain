<?php

namespace App\Ai\Agents;

/**
 * An agent with a human-readable name, shown in the AI log.
 */
interface LabelledAgent
{
    public static function label(): string;
}
