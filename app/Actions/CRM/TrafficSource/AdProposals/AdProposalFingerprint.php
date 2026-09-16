<?php

/*
 * Author: stewicca <stewicalf@gmail.com>
 * Copyright (c) 2026, Steven Wicca Alfredo
 */

namespace App\Actions\CRM\TrafficSource\AdProposals;

use App\Enums\CRM\TrafficSource\AdProposalTypeEnum;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Identifies the suggestion rather than the row, so tonight's run recognises what last night's run
 * proposed and a dismissal sticks for good.
 *
 * Built from the thing being acted on and never from the figures behind it. Were the numbers in here,
 * a term dismissed at three clicks would return at four wearing a new fingerprint, and the queue would
 * fill up with suggestions somebody has already refused.
 */
class AdProposalFingerprint
{
    use AsAction;

    /**
     * @param array<int, string|null> $parts
     */
    public function handle(AdProposalTypeEnum $type, array $parts): string
    {
        $normalised = collect($parts)
            ->filter()
            ->map(fn ($part) => mb_strtolower(trim((string) $part)))
            ->implode('|');

        return substr(hash('sha256', $type->value.'|'.$normalised), 0, 64);
    }
}
