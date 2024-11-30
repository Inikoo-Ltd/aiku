<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 19 Nov 2024 11:09:35 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Comms\Outbox\UI;

use App\Actions\InertiaAction;
use App\Models\Catalogue\Shop;
use App\Models\Comms\Outbox;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;

class EditOutbox extends InertiaAction
{
    use HasUIOutbox;

    public function handle(Outbox $outbox): Outbox
    {
        return $outbox;
    }

    public function authorize(ActionRequest $request): bool
    {
        $this->canEdit = $request->user()->hasPermissionTo('mail.edit');

        return $request->user()->hasPermissionTo("mail.edit");
    }

    public function inOrganisation(Outbox $outbox, ActionRequest $request): Outbox
    {
        $this->initialisation($request);

        return $this->handle($outbox);
    }

    /** @noinspection PhpUnusedParameterInspection */
    public function inShop(Shop $shop, Outbox $outbox, ActionRequest $request): Outbox
    {
        $this->initialisation($request);

        return $this->handle($outbox);
    }

    public function htmlResponse(Outbox $outbox, ActionRequest $request): Response
    {
        return Inertia::render(
            'EditModel',
            [
                'title'       => __('outbox'),
                'breadcrumbs' => $this->getBreadcrumbs($request->route()->getName(), $outbox),
                'pageHead'    => [
                    'title'    => $outbox->name,
                    'actions'  => [
                        [
                            'type'  => 'button',
                            'style' => 'exitEdit',
                            'route' => [
                                'name'       => preg_replace('/edit$/', 'show', $request->route()->getName()),
                                'parameters' => array_values($request->route()->originalParameters()),
                            ]
                        ]
                    ],
                ],

                'formData' => [
                    'blueprint' => [
                        [
                            'title'    => __('contact information'),
                            'fields'   => [

                                'name' => [
                                    'type'  => 'input',
                                    'label' => __('contact name'),
                                    'value' => $outbox->name
                                ],
                                'post_room_id' => [
                                    'type'  => 'input',
                                    'label' => __('post room'),
                                    'value' => $outbox->post_room_id
                                ],
                            ]
                        ]

                    ],
                    'args'      => [
                        'updateRoute' => [
                            'name'      => 'grp.models.outbox.update',
                            'parameters' => $outbox->id

                        ],
                    ]

                ],

            ]
        );
    }
}
