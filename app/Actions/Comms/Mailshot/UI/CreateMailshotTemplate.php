<?php

/*
 * Author: eka yudinata (https://github.com/ekayudinata)
 * Created: Wednesday, 21 Jan 2026 15:00:49 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2026, eka yudinata
 */

namespace App\Actions\Comms\Mailshot\UI;

use App\Actions\OrgAction;
use App\Models\Catalogue\Shop;
use App\Models\SysAdmin\Organisation;
use Exception;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;

class CreateMailshotTemplate extends OrgAction
{
    /**
     * @throws Exception
     */
    public function handle(Shop $parent, ActionRequest $request): Response
    {



        return Inertia::render(
            'CreateModel',
            [
                'breadcrumbs' => $this->getBreadcrumbs(
                    $parent->organisation,
                    $request->route()->getName(),
                    $request->route()->originalParameters()
                ),
                'title'    => __('New mailshot'),
                'pageHead' => [
                    'title' => __('New mailshot')
                ],
                'formData' => [
                    'fullLayout' => true,
                    'blueprint'  =>
                    [
                        [
                            'fields' => [
                                'name' => [
                                    'type'        => 'input',
                                    'label'       => __('Name'),
                                    'placeholder' => __('name'),
                                    'required'    => true,
                                    'value'       => '',
                                ],
                            ]
                        ]
                    ],
                    'route' => [
                        'name'       => 'grp.models.outbox.mailshot.store',
                        'parameters' => [
                            // 'outbox'         => $parent->outboxes()->where('outboxes.code', OutboxCodeEnum::MAILSHOT)->first()->id,
                        ]
                    ]
                ],

            ]
        );
    }

    // NOTE: update this path
    public function authorize(ActionRequest $request): bool
    {
        return $request->user()->authTo("crm.{$this->shop->id}.edit");
    }

    /**
     * @throws Exception
     */
    public function asController(Organisation $organisation, Shop $shop, ActionRequest $request): Response
    {
        $this->initialisationFromShop($shop, $request);

        return $this->handle($shop, $request);
    }

    // NOTE: update this path
    public function getBreadcrumbs(Organisation $parent, string $routeName, array $routeParameters): array
    {
        return array_merge(
            IndexMailshots::make()->getBreadcrumbs(
                routeName: $routeName,
                routeParameters: $routeParameters,
                parent: $parent
            ),
            [
                [
                    'type'          => 'creatingModel',
                    'creatingModel' => [
                        'label' => __('Creating mailshot'),
                    ]
                ]
            ]
        );
    }
}
