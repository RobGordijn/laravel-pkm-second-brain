<?php

namespace App\Enums;

enum ItemType: string
{
    case Note = 'note';
    case Idea = 'idea';
    case Todo = 'todo';
    case Link = 'link';
    case Quote = 'quote';
    case Journal = 'journal';
    case Synthesis = 'synthesis';

    /**
     * The types the classifier may choose for a fresh dump. Syntheses are made by saving an answer.
     *
     * @return list<self>
     */
    public static function classifiable(): array
    {
        return array_values(array_filter(self::cases(), fn (self $type) => $type !== self::Synthesis));
    }

    public function label(): string
    {
        return match ($this) {
            self::Note => 'Note',
            self::Idea => 'Idea',
            self::Todo => 'To-do',
            self::Link => 'Link',
            self::Quote => 'Quote',
            self::Journal => 'Journal',
            self::Synthesis => 'Synthesis',
        };
    }

    /**
     * Criteria the classifier uses to decide whether a dump belongs to this type.
     */
    public function description(): string
    {
        return match ($this) {
            self::Note => 'Reference information, facts or observations worth keeping',
            self::Idea => 'A new idea, plan, wish or possibility to explore later',
            self::Todo => 'Something the author has to do; contains one or more concrete actions',
            self::Link => 'Mainly a URL or bookmark to an article, video, tool or website',
            self::Quote => 'A quotation or passage written by someone else',
            self::Journal => 'Personal reflection, feelings or a log of what happened',
            self::Synthesis => 'An answer or write-up composed from several of the author\'s own notes',
        };
    }
}
