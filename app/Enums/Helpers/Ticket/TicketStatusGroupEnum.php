<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Enums\Helpers\Ticket;

use App\Enums\EnumHelperTrait;

enum TicketStatusGroupEnum: string
{
    use EnumHelperTrait;

    case TODO        = 'todo';
    case IN_PROGRESS = 'in_progress';
    case CLOSED      = 'closed';

    public static function labels(): array
    {
        return [
            'todo'        => __('Todo'),
            'in_progress' => __('In progress'),
            'closed'      => __('Closed'),
        ];
    }

    public static function stateIcon(): array
    {
        return [
            'todo'        => ['tooltip' => __('Todo'), 'icon' => 'fal fa-circle', 'class' => 'text-gray-500', 'color' => 'gray'],
            'in_progress' => ['tooltip' => __('In progress'), 'icon' => 'fal fa-spinner', 'class' => 'text-blue-500', 'color' => 'blue'],
            'closed'      => ['tooltip' => __('Closed'), 'icon' => 'fal fa-check-circle', 'class' => 'text-green-500', 'color' => 'green'],
        ];
    }

    /**
     * @return array<int, TicketStatusEnum>
     */
    public function statuses(): array
    {
        return array_values(array_filter(TicketStatusEnum::cases(), fn (TicketStatusEnum $status) => $status->group() === $this));
    }

    public function defaultStatus(): TicketStatusEnum
    {
        return $this->statuses()[0];
    }
}
