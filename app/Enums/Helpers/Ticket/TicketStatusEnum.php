<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 03 Sep 2026 10:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Enums\Helpers\Ticket;

use App\Enums\EnumHelperTrait;

enum TicketStatusEnum: string
{
    use EnumHelperTrait;

    case OPEN        = 'open';
    case IN_PROGRESS = 'in_progress';
    case WAITING     = 'waiting';
    case RESOLVED    = 'resolved';
    case CLOSED      = 'closed';

    public static function labels(): array
    {
        return [
            'open'        => __('Open'),
            'in_progress' => __('In progress'),
            'waiting'     => __('Waiting'),
            'resolved'    => __('Resolved'),
            'closed'      => __('Closed'),
        ];
    }

    public static function stateIcon(): array
    {
        return [
            'open'        => ['tooltip' => __('Open'), 'icon' => 'fal fa-circle', 'class' => 'text-blue-500', 'color' => 'blue'],
            'in_progress' => ['tooltip' => __('In progress'), 'icon' => 'fal fa-spinner', 'class' => 'text-amber-500', 'color' => 'amber'],
            'waiting'     => ['tooltip' => __('Waiting'), 'icon' => 'fal fa-clock', 'class' => 'text-gray-500', 'color' => 'gray'],
            'resolved'    => ['tooltip' => __('Resolved'), 'icon' => 'fal fa-check-circle', 'class' => 'text-green-500', 'color' => 'green'],
            'closed'      => ['tooltip' => __('Closed'), 'icon' => 'fal fa-times-circle', 'class' => 'text-gray-400', 'color' => 'gray'],
        ];
    }

    public function isOpen(): bool
    {
        return !in_array($this, [self::RESOLVED, self::CLOSED]);
    }
}
