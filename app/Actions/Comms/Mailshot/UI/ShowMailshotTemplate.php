<?php

/*
 * Author: eka yudinata (https://github.com/ekayudinata)
 * Created: Wednesday, 22 Jan 2026 15:00:49 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2026, eka yudinata
 */

namespace App\Actions\Comms\Mailshot\UI;

use App\Actions\OrgAction;
use App\Models\Catalogue\Shop;
use App\Models\Comms\EmailTemplate;
use App\Actions\Traits\Authorisations\WithCatalogueAuthorisation;
use App\Models\SysAdmin\Organisation;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;

class ShowMailshotTemplate extends OrgAction
{
    // use WithCatalogueAuthorisation;

    public function handle(EmailTemplate $emailTemplate): EmailTemplate
    {
        return $emailTemplate;
    }


    public function asController(Organisation $organisation, Shop $shop, EmailTemplate $emailTemplate, ActionRequest $request): EmailTemplate
    {
        $this->initialisationFromShop($shop, $request);

        return $this->handle($emailTemplate);
    }



    public function htmlResponse(EmailTemplate $emailTemplate, ActionRequest $request): Response
    {

        return Inertia::render(
            'Comms/MailshotTemplate',
            [
                'title'                           => $emailTemplate->name,
                'breadcrumbs'                     => $this->getBreadcrumbs(
                    $emailTemplate,
                    $request->route()->getName(),
                    $request->route()->originalParameters(),
                ),
                'pageHead'                        => [
                    'icon'    => 'fal fa-coins',
                    'title'   => $emailTemplate->name,
                    'edit'    => $this->canEdit ? [
                        'route' => [
                            'name'       => preg_replace('/show$/', 'edit', $request->route()->getName()),
                            'parameters' => array_values($request->route()->originalParameters())
                        ]
                    ] : false,
                    'actions' => [
                        [
                            'type'  => 'button',
                            'style' => 'edit',
                            'label' => __('Workshop'),
                            'icon'  => ["fal", "fa-drafting-compass"],
                            'route' => [
                                'name'       => "grp.org.shops.show.marketing.mailshots.workshop",
                                'parameters' => [
                                    $this->organisation->slug,
                                    $this->shop->slug,
                                    $emailTemplate->slug
                                ]
                            ]
                        ],
                        [
                            'type'  => 'button',
                            'style' => 'edit',
                            'label' => __('Edit'),
                            'icon'  => ["fal", "fa-sliders-h"],
                            'route' => [
                                'name'       => "grp.org.shops.show.marketing.mailshots.workshop",
                                'parameters' => [
                                    $this->organisation->slug,
                                    $this->shop->slug,
                                    $emailTemplate->slug
                                ]
                            ]
                        ]
                    ]
                ],
                'deleteMailshotRoute' => [
                    'name' => 'grp.models.shop.mailshot.delete',
                    'parameters' => [
                        'shop' => $this->shop->id,
                        'emailTemplate' => $emailTemplate->id
                    ],
                ],
                'indexRoute' => [
                    'name' => 'grp.org.shops.show.marketing.email-templates.index',
                    'parameters' => [
                        'organisation' => $this->organisation->slug,
                        'shop' => $this->shop->slug
                    ],
                ],
            ]
        );
    }


    // TODO: Fix breadcrumbs
    public function getBreadcrumbs(EmailTemplate $emailTemplate, string $routeName, array $routeParameters, string $suffix = ''): array
    {
        $headCrumb = function (EmailTemplate $emailTemplate, array $routeParameters, string $suffix) {

            return [
                [
                    'type'           => 'modelWithIndex',
                    'modelWithIndex' => [
                        'index' => [
                            'route' => $routeParameters['index'],
                            'label' => "Test Template"
                        ],
                        'model' => [
                            'route' => $routeParameters['model'],
                            'label' => $emailTemplate->name,
                        ],

                    ],
                    'suffix'         => $suffix
                ],
            ];
        };


        return match ($routeName) {


            // 'grp.org.shops.show.marketing.mailshots.show' => array_merge(
            //     MarketingHub::make()->getBreadcrumbs(
            //         'grp.marketing.shops.show.hub',
            //         Arr::only($routeParameters, ['organisation', 'shop']),
            //     ),
            //     $headCrumb(
            //         $mailshot,
            //         [
            //             'index' => [
            //                 'name'       => 'grp.org.shops.show.marketing.mailshots.index',
            //                 'parameters' => Arr::only($routeParameters, ['organisation', 'shop'])
            //             ],
            //             'model' => [
            //                 'name'       => 'grp.org.shops.show.marketing.mailshots.show',
            //                 'parameters' => Arr::only($routeParameters, ['organisation', 'shop', 'mailshot'])
            //             ]
            //         ],
            //         $suffix
            //     )
            // ),
            // 'grp.org.shops.show.marketing.newsletters.show' => array_merge(
            //     MarketingHub::make()->getBreadcrumbs(
            //         'grp.marketing.shops.show.hub',
            //         Arr::only($routeParameters, ['organisation', 'shop']),
            //     ),
            //     $headCrumb(
            //         $mailshot,
            //         [
            //             'index' => [
            //                 'name'       => 'grp.org.shops.show.marketing.newsletters.index',
            //                 'parameters' => Arr::only($routeParameters, ['organisation', 'shop'])
            //             ],
            //             'model' => [
            //                 'name'       => 'grp.org.shops.show.marketing.newsletters.show',
            //                 'parameters' => Arr::only($routeParameters, ['organisation', 'shop', 'mailshot'])
            //             ]
            //         ],
            //         $suffix
            //     )
            // ),


            default => []
        };
    }
}
