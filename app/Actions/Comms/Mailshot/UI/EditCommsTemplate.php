<?php

/*
 * Author: eka yudinata (https://github.com/ekayudinata)
 * Created: Wednesday, 07 Oct 2026 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2026, eka yudinata
 */

namespace App\Actions\Comms\Mailshot\UI;

use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\WithMarketingEditAuthorisation;
use App\Models\Catalogue\Shop;
use App\Models\Comms\EmailTemplate;
use App\Models\SysAdmin\Organisation;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;

class EditCommsTemplate extends OrgAction
{
    use WithMarketingEditAuthorisation;

    public function handle(Shop $shop, EmailTemplate $emailTemplate, ActionRequest $request): Response
    {
        return Inertia::render(
            'EditModel',
            [
                'breadcrumbs' => $this->getBreadcrumbs(
                    $emailTemplate,
                    $request->route()->originalParameters()
                ),
                'title'       => __('Edit template'),
                'pageHead'    => [
                    'title'   => __('Edit template'),
                    'actions' => [
                        [
                            'type'  => 'button',
                            'style' => 'exitEdit',
                            'label' => __('Exit edit'),
                            'route' => [
                                'name'       => 'grp.org.shops.show.dashboard.comms.templates.workshop',
                                'parameters' => [
                                    'organisation'  => $shop->organisation->slug,
                                    'shop'          => $shop->slug,
                                    'emailTemplate' => $emailTemplate->slug
                                ]
                            ]
                        ]
                    ]
                ],
                'formData'    => [
                    'fullLayout' => true,
                    'blueprint'  => [
                        [
                            'fields' => [
                                'name'          => [
                                    'type'        => 'input',
                                    'label'       => __('Name'),
                                    'placeholder' => __('name'),
                                    'required'    => true,
                                    'value'       => $emailTemplate->name,
                                ],
                                'dynamic_block' => [
                                    'type'        => 'toggle',
                                    'label'       => __('For dynamic content block'),
                                    'information' => __('Dynamic blocks can be inserted into emails from the Dynamic content block'),
                                    'value'       => (bool) data_get($emailTemplate->data, 'dynamic_block', false),
                                ],
                            ]
                        ]
                    ],
                    'args'       => [
                        'updateRoute' => [
                            'name'       => 'grp.models.shop.email-template.update',
                            'parameters' => [
                                'shop'          => $shop->id,
                                'emailTemplate' => $emailTemplate->id,
                            ]
                        ],
                    ],
                ],
            ]
        );
    }

    public function asController(Organisation $organisation, Shop $shop, EmailTemplate $emailTemplate, ActionRequest $request): Response
    {
        $this->initialisationFromShop($shop, $request);

        return $this->handle($shop, $emailTemplate, $request);
    }

    public function getBreadcrumbs(EmailTemplate $emailTemplate, array $routeParameters): array
    {
        return array_merge(
            IndexMailshotTemplates::make()->getBreadcrumbs(
                'grp.org.shops.show.dashboard.comms.templates.index',
                $routeParameters,
                parent: $this->shop
            ),
            [
                [
                    'type'   => 'simple',
                    'simple' => [
                        'route' => $routeParameters,
                        'label' => $emailTemplate->name,
                    ],
                    'suffix' => __('(Edit)'),
                ]
            ]
        );
    }
}
