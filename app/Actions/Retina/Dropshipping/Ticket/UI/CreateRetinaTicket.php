<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 03 Sep 2026 10:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Retina\Dropshipping\Ticket\UI;

use App\Actions\RetinaAction;
use App\Enums\CRM\Livechat\ChatPriorityEnum;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;

class CreateRetinaTicket extends RetinaAction
{
    public function asController(ActionRequest $request): Response
    {
        $this->initialisation($request);

        return Inertia::render(
            'Dropshipping/RetinaCreateTicket',
            [
                'breadcrumbs' => array_merge(
                    IndexRetinaTickets::make()->getBreadcrumbs(),
                    [['type' => 'creatingModel', 'creatingModel' => ['label' => __('New ticket')]]]
                ),
                'title'       => __('New ticket'),
                'pageHead'    => [
                    'title'   => __('New support ticket'),
                    'icon'    => 'fal fa-life-ring',
                    'actions' => [
                        [
                            'type'  => 'button',
                            'style' => 'cancel',
                            'label' => __('Cancel'),
                            'route' => ['name' => 'retina.dropshipping.tickets.index'],
                        ],
                    ],
                ],
                'storeRoute'  => ['name' => 'retina.models.ticket.store'],
                'priorities'  => collect(ChatPriorityEnum::labels())->map(fn ($label, $value) => ['label' => $label, 'value' => $value])->values(),
            ]
        );
    }
}
