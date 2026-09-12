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
    case ASSIGNED    = 'assigned';
    case IN_PROGRESS = 'in_progress';
    case WAITING     = 'waiting';
    case RESOLVED    = 'resolved';
    case CANCELLED   = 'cancelled';

    public static function labels(): array
    {
        return [
            'open'        => __('Todo'),
            'assigned'    => __('Assigned'),
            'in_progress' => __('In progress'),
            'waiting'     => __('Waiting'),
            'resolved'    => __('Done'),
            'cancelled'   => __('Cancelled'),
        ];
    }

    public static function stateIcon(): array
    {
        return [
            'open'        => ['tooltip' => __('Todo'), 'icon' => 'fal fa-circle', 'class' => 'text-gray-500', 'color' => 'gray'],
            'assigned'    => ['tooltip' => __('Assigned'), 'icon' => 'fal fa-user-check', 'class' => 'text-gray-600', 'color' => 'gray'],
            'in_progress' => ['tooltip' => __('In progress'), 'icon' => 'fal fa-spinner', 'class' => 'text-blue-500', 'color' => 'blue'],
            'waiting'     => ['tooltip' => __('Waiting'), 'icon' => 'fal fa-clock', 'class' => 'text-blue-400', 'color' => 'blue'],
            'resolved'    => ['tooltip' => __('Done'), 'icon' => 'fal fa-check-circle', 'class' => 'text-green-500', 'color' => 'green'],
            'cancelled'   => ['tooltip' => __('Cancelled'), 'icon' => 'fal fa-ban', 'class' => 'text-green-700', 'color' => 'green'],
        ];
    }

    public function group(): TicketStatusGroupEnum
    {
        return match ($this) {
            self::OPEN, self::ASSIGNED           => TicketStatusGroupEnum::TODO,
            self::IN_PROGRESS, self::WAITING     => TicketStatusGroupEnum::IN_PROGRESS,
            self::RESOLVED, self::CANCELLED      => TicketStatusGroupEnum::CLOSED,
        };
    }

    public function isOpen(): bool
    {
        return $this->group() !== TicketStatusGroupEnum::CLOSED;
    }
}
