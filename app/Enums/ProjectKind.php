<?php

namespace App\Enums;

/**
 * The actionable half of PARA: projects have a goal and an end, areas are ongoing responsibilities.
 */
enum ProjectKind: string
{
    case Project = 'project';
    case Area = 'area';

    public function label(): string
    {
        return match ($this) {
            self::Project => 'Project',
            self::Area => 'Area',
        };
    }

    public function pluralLabel(): string
    {
        return match ($this) {
            self::Project => 'Projects',
            self::Area => 'Areas',
        };
    }
}
