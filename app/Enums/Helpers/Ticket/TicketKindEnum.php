<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 05 Sep 2026 10:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Enums\Helpers\Ticket;

use App\Models\Helpers\Ticket;
use App\Models\SysAdmin\User;
use App\Enums\EnumHelperTrait;

enum TicketKindEnum: string
{
    use EnumHelperTrait;

    case ESCALATION = 'escalation';
    case BUG        = 'bug';
    case FEATURE    = 'feature';
    case TASK       = 'task';
    case QA         = 'qa';

    public static function labels(): array
    {
        return [
            'escalation' => __('Escalated customer ticket'),
            'bug'        => __('Bug'),
            'feature'    => __('Feature request'),
            'task'       => __('Engineering task'),
            'qa'         => __('QA check request'),
        ];
    }

    /** @return array<int, string> */
    public static function internalValues(): array
    {
        return [self::TASK->value, self::QA->value];
    }

    /** @return array<int, array{label: string, value: string}> */
    public static function raisableBy(User $user): array
    {
        return collect(self::labels())
            ->except(Ticket::canBeManagedBy($user) ? ['escalation'] : ['escalation', ...self::internalValues()])
            ->map(fn ($label, $value) => ['label' => $label, 'value' => $value])
            ->values()
            ->all();
    }
}
