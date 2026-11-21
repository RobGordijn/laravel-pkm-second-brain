<?php

namespace App\Enums;

enum ItemStatus: string
{
    case Pending = 'pending';
    case Processing = 'processing';
    case NeedsFeedback = 'needs_feedback';
    case Ready = 'ready';
    case Failed = 'failed';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Queued',
            self::Processing => 'Processing',
            self::NeedsFeedback => 'Awaiting answer',
            self::Ready => 'Ready',
            self::Failed => 'Failed',
        };
    }

    public function isInProgress(): bool
    {
        return in_array($this, [self::Pending, self::Processing], true);
    }
}
