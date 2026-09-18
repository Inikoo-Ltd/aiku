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
use App\Enums\Helpers\Ticket\TicketTypeEnum;
use App\Enums\Helpers\Ticket\TicketModuleEnum;
use App\Models\Catalogue\Shop;
use App\Models\SysAdmin\Organisation;
use Inertia\Inertia;
use Inertia\Response;
use App\Models\Helpers\Ticket;
use Lorisleiva\Actions\ActionRequest;

class CreateTicket extends OrgAction
{
    use WithTicketsScope;

    public function authorize(ActionRequest $request): bool
    {
        return Ticket::canBeRaisedBy($request->user());
    }

    public function asController(ActionRequest $request): Response
    {
        $this->initialisationFromTicketsScope($request);

        return $this->renderCreateTicket($request);
    }

    public function inOrganisation(Organisation $organisation, ActionRequest $request): Response
    {
        $this->initialisationFromTicketsScope($request, $organisation);

        return $this->renderCreateTicket($request);
    }

    public function inShop(Organisation $organisation, Shop $shop, ActionRequest $request): Response
    {
        $this->initialisationFromTicketsScope($request, $organisation, $shop);

        return $this->renderCreateTicket($request);
    }

    private function renderCreateTicket(ActionRequest $request): Response
    {
        return Inertia::render(
            'Tickets/CreateTicket',
            [
                'breadcrumbs' => array_merge(
                    $this->ticketsBreadcrumbs(),
                    [['type' => 'creatingModel', 'creatingModel' => ['label' => __('Creating ticket')]]]
                ),
                'title'       => __('Create New Ticket'),
                'pageHead'    => [
                    'title'   => __('Create New Ticket'),
                    'icon'    => ['fal', 'fa-life-ring'],
                    'actions' => [
                        [
                            'type'  => 'button',
                            'style' => 'cancel',
                            'label' => __('Cancel'),
                            'route' => $this->ticketsRoute('index'),
                        ],
                    ],
                ],
                'storeRoute'  => ['name' => 'grp.models.ticket.store'],
                'priorities'  => collect(ChatPriorityEnum::labels())->map(fn ($label, $value) => ['label' => $label, 'value' => $value])->values(),
                'modules'     => collect(TicketModuleEnum::labels())->map(fn ($label, $value) => ['label' => $label, 'value' => $value])->values(),
                'kinds'       => TicketKindEnum::raisableBy($request->user()),
                'types'       => Ticket::canChooseType($request->user())
                    ? collect(TicketTypeEnum::cases())->map(fn (TicketTypeEnum $type) => ['label' => TicketTypeEnum::labels()[$type->value], 'value' => $type->value])->values()
                    : [],
            ]
        );
    }
}
