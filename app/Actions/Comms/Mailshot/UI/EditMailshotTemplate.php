<?php

/*
 * Author: eka yudinata (https://github.com/ekayudinata)
 * Created: Wednesday, 21 Jan 2026 15:00:49 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2026, eka yudinata
 */

namespace App\Actions\Comms\Mailshot\UI;

use App\Actions\OrgAction;
use App\Models\Catalogue\Shop;
use App\Models\Comms\EmailTemplate;
use App\Models\SysAdmin\Organisation;
use Exception;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;

class EditMailshotTemplate extends OrgAction
{
    /**
     * @throws Exception
     */
    public function handle(Shop $parent, EmailTemplate $emailTemplate, ActionRequest $request): Response
    {

        return Inertia::render(
            'EditModel',
            [
                'breadcrumbs' => $this->getBreadcrumbs(
                    $parent->organisation,
                    $request->route()->getName(),
                    $request->route()->originalParameters()
                ),
                'title'    => __('Edit template'),
                'pageHead' => [
                    'title' => __('Edit template'),
                    'actions' => [
                        [
                            'type'  => 'button',
                            'style' => 'exitEdit',
                            'label' => __('Exit edit'),
                            'route' => [
                                'name'       => 'grp.org.shops.show.marketing.templates.workshop',
                                'parameters' => [
                                    'organisation' => $parent->organisation->slug,
                                    'shop' => $parent->slug,
                                    'emailTemplate' => $emailTemplate->slug
                                ]
                            ]
                        ]
                    ]
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
                                    'value'       => $emailTemplate->name,
                                ],
                            ]
                        ]
                    ],
                    'args'       => [
                        'updateRoute' => [
                            'name'       => 'grp.models.shop.email-template.update',
                            'parameters' => [
                                'shop' => $parent->id,
                                'emailTemplate' => $emailTemplate->id,
                            ]

                        ],
                    ],
                ],

            ]
        );
    }

    // TODO: update this path
    public function authorize(ActionRequest $request): bool
    {
        return $request->user()->authTo("crm.{$this->shop->id}.edit");
    }

    /**
     * @throws Exception
     */
    public function asController(Organisation $organisation, Shop $shop, EmailTemplate $emailTemplate, ActionRequest $request): Response
    {
        $this->initialisationFromShop($shop, $request);

        return $this->handle($shop, $emailTemplate, $request);
    }

    // TODO: update this path
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
                        'label' => __('Creating template'),
                    ]
                ]
            ]
        );
    }
}
