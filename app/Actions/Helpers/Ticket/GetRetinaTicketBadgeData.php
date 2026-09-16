<?php

/*
 * Author Louis Perez
 * Created on 15-09-2026-15h-43m
 * GitHub: https://github.com/louis-perez
 * Copyright 2026
*/

namespace App\Actions\Helpers\Ticket;

use App\Enums\Helpers\Ticket\TicketStatusEnum;
use App\Models\CRM\WebUser;
use App\Models\Helpers\Ticket;
use Lorisleiva\Actions\Concerns\AsObject;

class GetRetinaTicketBadgeData
{
    use AsObject;

    /**
     * @return array{mine: array<string, array{label: string, count: int}>, recent: array<int, array<string, mixed>>}
     */
    public function handle(WebUser $webUser): array
    {
        $tickets = Ticket::where('customer_id', $webUser->customer_id);

        return [
            'mine'   => [
                'to_do'       => ['label' => __('To do'), 'count' => (clone $tickets)->whereIn('status', [TicketStatusEnum::OPEN, TicketStatusEnum::ASSIGNED])->count()],
                'in_progress' => ['label' => __('In progress'), 'count' => (clone $tickets)->whereIn('status', [TicketStatusEnum::IN_PROGRESS, TicketStatusEnum::ANSWERED, TicketStatusEnum::PENDING_DEPLOY])->count()],
                'waiting'     => ['label' => __('Waiting for my reply'), 'count' => (clone $tickets)->where('status', TicketStatusEnum::WAITING)->count()],
            ],
            'recent' => GetTicketBadgeData::make()->recentUpdates($webUser),
        ];
    }
}
