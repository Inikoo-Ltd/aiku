<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 03 Sep 2026 10:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Helpers\Ticket\UI;

use App\Actions\OrgAction;
use App\Enums\CRM\Livechat\ChatPriorityEnum;
use App\Enums\Helpers\Ticket\TicketKindEnum;
use App\Enums\Helpers\Ticket\TicketModuleEnum;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;

class CreateTicket extends OrgAction
{
    public function authorize(ActionRequest $request): bool
    {
        return $request->user() !== null;
    }

    public function asController(ActionRequest $request): Response
    {
        $this->initialisationFromGroup(group(), $request);

        return Inertia::render(
            'Tickets/CreateTicket',
            [
                'breadcrumbs' => array_merge(
                    IndexTickets::make()->getBreadcrumbs(),
                    [['type' => 'creatingModel', 'creatingModel' => ['label' => __('Creating ticket')]]]
                ),
                'title'       => __('New ticket'),
                'pageHead'    => [
                    'title'   => __('New ticket'),
                    'icon'    => ['fal', 'fa-life-ring'],
                    'actions' => [
                        [
                            'type'  => 'button',
                            'style' => 'cancel',
                            'label' => __('Cancel'),
                            'route' => ['name' => 'grp.tickets.index'],
                        ],
                    ],
                ],
                'storeRoute'  => ['name' => 'grp.models.ticket.store'],
                'priorities'  => collect(ChatPriorityEnum::labels())->map(fn ($label, $value) => ['label' => $label, 'value' => $value])->values(),
                'modules'     => collect(TicketModuleEnum::labels())->map(fn ($label, $value) => ['label' => $label, 'value' => $value])->values(),
                'kinds'       => collect(TicketKindEnum::labels())->except('escalation')->map(fn ($label, $value) => ['label' => $label, 'value' => $value])->values(),
            ]
        );
    }
}
