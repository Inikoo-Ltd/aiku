<?php

/*
 * Author: stewicca <stewicalf@gmail.com>
 * Copyright (c) 2026, Steven Wicca Alfredo
 */

namespace App\Enums\CRM\TrafficSource;

use App\Enums\EnumHelperTrait;

/**
 * Where a proposal has got to.
 *
 * `dismissed` is permanent on purpose and is the reason the queue stays readable. A suggestion turned
 * down once must never come back, or within a fortnight nobody opens the page. The fingerprint on the
 * row is what makes that stick across regenerations.
 *
 * `stale` is the honest ending for a proposal whose evidence no longer holds by the time somebody
 * approves it: the figures are re-read at that moment, and a proposal that was true on Monday and is
 * not true on Friday is retired rather than applied.
 */
enum AdProposalStateEnum: string
{
    use EnumHelperTrait;

    case OPEN = 'open';
    case APPLIED = 'applied';
    case DISMISSED = 'dismissed';
    case STALE = 'stale';
    case FAILED = 'failed';

    public static function labels(): array
    {
        return [
            self::OPEN->value      => 'Waiting on you',
            self::APPLIED->value   => 'Applied',
            self::DISMISSED->value => 'Dismissed',
            self::STALE->value     => 'No longer true',
            self::FAILED->value    => 'Google refused it',
        ];
    }

    /** Only an open proposal is worth regenerating around; the rest are settled. */
    public function isSettled(): bool
    {
        return $this !== self::OPEN;
    }
}
