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
            'Org/Web/Workshop/Mailshot/MailshotTemplateWorkshop',
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
                                'name'       => 'grp.org.shops.show.marketing.templates.index',
                                'parameters' => [
                                    'organisation' => $this->organisation->slug,
                                    'shop' => $this->shop->slug
                                ]
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
                'builder'     => $emailTemplate->builder,
                'snapshot'    => $emailTemplate->layout,
                'updateRoute'         => [
                    'name'       => 'grp.models.shop.email-template.update',
                    'parameters' => [
                        'shop' => $emailTemplate->shop_id,
                        'emailTemplate' => $emailTemplate->id
                    ],
                    'method' => 'patch'
                ],
                'storeTemplateRoute' => [
                    'name' => 'grp.models.shop.email-template.store.as-new-template',
                    'parameters' => [
                        'shop' => $emailTemplate->shop_id,
                        'emailTemplate' => $emailTemplate->id
                    ],
                    'method' => 'post'
                ],
                'mergeTags' => GetMailshotMergeTags::run(),
                'organisationSlug' => $this->organisation->slug,
            ]
        );
    }

    // TODO: Update later
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
                    'suffix' => __('(Workshop)'),
                ]
            ];
        };


        return match ($routeName) {
            'grp.org.shops.show.marketing.templates.workshop' =>
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
