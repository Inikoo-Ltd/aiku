<?php

/*
 * Author: eka yudinata (https://github.com/ekayudinata)
 * Created: Wednesday, 21 Jan 2026 15:00:49 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2026, eka yudinata
 */

namespace App\Actions\Comms\Mailshot\UI;

use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\WithMarketingEditAuthorisation;
use App\Models\Catalogue\Shop;
use App\Models\Comms\EmailTemplate;
use App\Models\SysAdmin\Organisation;
use Exception;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;

class EditMailshotTemplate extends OrgAction
{
    use WithMarketingEditAuthorisation;
    /**
     * @throws Exception
     */
    public function handle(Shop $parent, EmailTemplate $emailTemplate, ActionRequest $request): Response
    {

        return Inertia::render(
            'EditModel',
            [
                'breadcrumbs' => $this->getBreadcrumbs(
                    $emailTemplate,
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

    /**
     * @throws Exception
     */
    public function asController(Organisation $organisation, Shop $shop, EmailTemplate $emailTemplate, ActionRequest $request): Response
    {
        $this->initialisationFromShop($shop, $request);

        return $this->handle($shop, $emailTemplate, $request);
    }

    public function getBreadcrumbs(EmailTemplate $emailTemplate, string $routeName, array $routeParameters, string $suffix = null): array
    {
        $headCrumb = function (EmailTemplate $emailTemplate, array $routeParameters, string $suffix = null) {
            return [
                [
                    'type' => 'simple',
                    'simple' => [
                        'route' => $routeParameters,
                        'label' => $emailTemplate->name,
                    ],
                    'suffix' => __('(Edit)'),
                ]
            ];
        };


        return match ($routeName) {
            'grp.org.shops.show.marketing.templates.edit' =>
            array_merge(
                IndexMailshotTemplates::make()->getBreadcrumbs(
                    'grp.org.shops.show.marketing.templates.index',
                    $routeParameters,
                    parent: $this->shop
                ),
                $headCrumb(
                    $emailTemplate,
                    $routeParameters,
                ),
            ),
            default => []
        };
    }
}
