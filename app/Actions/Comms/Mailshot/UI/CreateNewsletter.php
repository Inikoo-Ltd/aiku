<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 19 Nov 2024 11:09:35 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Comms\Mailshot\UI;

use App\Actions\OrgAction;
use App\Enums\Comms\Outbox\OutboxCodeEnum;
use App\Models\Catalogue\Shop;
use App\Models\SysAdmin\Organisation;
use Exception;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;

class CreateNewsletter extends OrgAction
{
    /**
     * @throws Exception
     */
    public function handle(Shop $shop, ActionRequest $request): Response
    {
        $outbox = $shop->outboxes()->where('outboxes.code', OutboxCodeEnum::NEWSLETTER)->first();

        $fields[] = [
            'title'  => '',
            'fields' => [
                'subject' => [
                    'type'        => 'input',
                    'label'       => __('subject'),
                    'placeholder' => __('Email subject'),
                    'required'    => true,
                    'value'       => '',
                ],
            ]
        ];


        $title = __('New newsletter');

        return Inertia::render(
            'CreateModel',
            [
                'breadcrumbs' => $this->getBreadcrumbs(
                    $shop->organisation,
                    $request->route()->getName(),
                    $request->route()->originalParameters()
                ),
                'title'       => $title,
                'pageHead'    => [
                    'title' => $title
                ],
                'formData'    => [
                    'fullLayout' => true,
                    'blueprint'  =>
                        [
                            [
                                'title'  => __('name'),
                                'fields' => array_merge(...array_map(fn ($item) => $item['fields'], $fields))
                            ]
                        ],
                    'route'      => [
                        'name'       => 'grp.models.outbox.mailshot.store',
                        'parameters' => [
                            'outbox' => $outbox->id,
                        ]
                    ]
                ],

            ]
        );
    }


    /**
     * @throws Exception
     */
    public function asController(Organisation $organisation, Shop $shop, ActionRequest $request): Response
    {
        $this->initialisationFromShop($shop, $request);

        return $this->handle($shop, $request);
    }

    public function getBreadcrumbs(Organisation $organisation, string $routeName, array $routeParameters): array
    {
        return array_merge(
            IndexMailshots::make()->getBreadcrumbs(
                routeName: $routeName,
                routeParameters: $routeParameters,
                parent: $organisation
            ),
            [
                [
                    'type'          => 'creatingModel',
                    'creatingModel' => [
                        'label' => __('Creating newsletter'),
                    ]
                ]
            ]
        );
    }

}
