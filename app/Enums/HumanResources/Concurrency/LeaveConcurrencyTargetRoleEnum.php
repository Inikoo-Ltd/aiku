<?php

namespace App\Enums\HumanResources\Concurrency;

enum LeaveConcurrencyTargetRoleEnum: string
{
    case SUBJECT   = 'subject';
    case DEPENDENT = 'dependent';

    public function label(): string
    {
        return match ($this) {
            self::SUBJECT   => __('Subject (Blocker)'),
            self::DEPENDENT => __('Dependent (Blocked)'),
        };
    }
}
