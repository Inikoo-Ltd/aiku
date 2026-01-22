<?php

/*
 * Author: eka yudinata (https://github.com/ekayudinata)
 * Created: Thursday, 22 Jan 2026 10:16:30 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2026, eka yudinata
 */

namespace App\Actions\Comms\Mailshot\UI;

use App\Actions\Comms\Mailshot\GetMailshotMergeTags;
use App\Actions\OrgAction;
use App\Actions\Traits\Actions\WithActionButtons;
use App\Models\Catalogue\Shop;
use App\Models\SysAdmin\Organisation;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;
use App\Actions\Traits\WithOutboxBuilder;
use App\Models\Comms\EmailTemplate;

class ShowMailshotTemplateWorkshop extends OrgAction
{
    use WithActionButtons;
    use WithOutboxBuilder;

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
            'Org/Web/Workshop/Mailshot/MailshotTemplateWorkshop', // TODO: Update this patch later
            [
                'breadcrumbs' => $this->getBreadcrumbs(
                    $emailTemplate,
                    $request->route()->getName(),
                    $request->route()->originalParameters()
                ),
                'title'       => $emailTemplate->name,
                'pageHead'    => [
                    'title'     => $emailTemplate->name,
                    'icon'      => [
                        'tooltip' => __('snapshot'),
                        'icon'    => 'fal fa-mail-bulk'
                    ],
                    'actions'   => [
                        [
                            'type'  => 'button',
                            'style' => 'exit',
                            'label' => __('Exit workshop'),
                            'route' => [
                                'name'       => preg_replace('/workshop$/', 'show', $request->route()->getName()),
                                'parameters' => array_values($request->route()->originalParameters()),
                            ]
                        ],
                        [
                            'type'  => 'button',
                            'style' => 'edit',
                            'label' => __('Edit'),
                            'icon'  => ["fal", "fa-sliders-h"],
                            'route' => [
                                'name'       => "grp.org.shops.show.marketing.templates.edit",
                                'parameters' => [
                                    $this->organisation->slug,
                                    $this->shop->slug,
                                    $emailTemplate->slug
                                ]
                            ]
                        ]
                    ]

                ],
                'unpublished_layout' => $emailTemplate->layout,
                'snapshot'    => $emailTemplate->layout,
                'builder'     => $emailTemplate->builder,
                // TODO: check and make sure this route
                'imagesUploadRoute'   => [
                    'name'       => 'grp.models.email-template.images.store',
                    'parameters' => $emailTemplate->id
                ],
                // TODO: check and make sure this route
                'updateRoute'         => [
                    'name'       => 'grp.models.shop.email-template.workshop.update',
                    'parameters' => [
                        'shop' => $emailTemplate->shop_id,
                        'mailshot' => $emailTemplate->id
                    ],
                    'method' => 'patch'
                ],
                // TODO: check and make sure this route
                'loadRoute'           => [
                    'name'       => 'grp.models.email-templates.content.show',
                    'parameters' => $emailTemplate->id
                ],
                'mergeTags' => GetMailshotMergeTags::run(),
                'organisationSlug' => $this->organisation->slug,
            ]
        );
    }

    // TODO: Update later
    public function getBreadcrumbs(EmailTemplate $emailTemplate, string $routeName, array $routeParameters, string $suffix = null): array
    {
        $headCrumb = function (string $type, EmailTemplate $emailTemplate, array $routeParameters, string $suffix = null) {
            return [
                [
                    'type'           => $type,
                    'modelWithIndex' => [
                        'index' => [
                            'route' => $routeParameters['index'],
                            'label' => __('Mailshots')
                        ],
                        'model' => [
                            'route' => $routeParameters['model'],
                            'label' => $emailTemplate->name,
                        ],

                    ],
                    'simple'         => [
                        'route' => $routeParameters['model'],
                        'label' => $emailTemplate->name
                    ],
                    'suffix'         => $suffix
                ],
            ];
        };


        return match ($routeName) {
            // 'grp.org.shops.show.marketing.mailshots.workshop' =>
            // array_merge(
            //     ShowMailshot::make()->getBreadcrumbs(
            //         $mailshot,
            //         'grp.org.shops.show.marketing.mailshots.show',
            //         $routeParameters,
            //     ),
            //     $headCrumb(
            //         'modelWithIndex',
            //         $mailshot,
            //         [
            //             'index' => [
            //                 'name'       => 'grp.org.shops.show.marketing.mailshots.index',
            //                 'parameters' => $routeParameters
            //             ],
            //             'model' => [
            //                 'name'       => 'grp.org.shops.show.marketing.mailshots.show',
            //                 'parameters' => $routeParameters
            //             ]
            //         ],
            //         $suffix
            //     ),
            // ),
            default => []
        };
    }
}
