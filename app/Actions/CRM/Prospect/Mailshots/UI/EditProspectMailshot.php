<?php

/*
 * Author: eka yudinata (https://github.com/ekayudinata)
 * Created: Friday, 28 Feb 2025 14:26:17 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2025, eka yudinata
 */

namespace App\Actions\CRM\Prospect\Mailshots\UI;

use App\Actions\OrgAction;
use App\Actions\Traits\WithProspectsSubNavigation;
use App\Models\Catalogue\Shop;
use App\Models\Comms\Mailshot;
use App\Models\SysAdmin\Organisation;
use Exception;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;

class EditProspectMailshot extends OrgAction
{
    use WithProspectsSubNavigation;

    /**
     * @throws Exception
     */
    public function handle(Mailshot $mailshot, ActionRequest $request): Response
    {
        $fields[] = [
            'title'  => '',
            'fields' => [
                'subject'        => [
                    'type'        => 'input',
                    'label'       => __('Subject'),
                    'placeholder' => __('Email subject'),
                    'required'    => true,
                    'value'       => $mailshot->subject,
                ],
            ]
        ];

        return Inertia::render(
            'EditModel',
            [
                'breadcrumbs' => $this->getBreadcrumbs(
                    $mailshot,
                    $request->route()->getName(),
                    $request->route()->originalParameters()
                ),
                'title'       => __('Edit prospect mailshot'),
                'pageHead'    => [
                    'title' => __('Edit prospect mailshot'),
                    'actions' => [
                        [
                            'type'  => 'button',
                            'style' => 'exitEdit',
                            'label' => __('Exit edit'),
                            'route' => [
                                'name'       => preg_replace('/edit$/', 'show', $request->route()->getName()),
                                'parameters' => array_values($request->route()->originalParameters())
                            ]
                        ]
                    ],
                ],
                'formData'    => [
                    'fullLayout' => true,
                    'blueprint'  =>
                    [
                        [
                            'title'  => '',
                            'fields' => array_merge(...array_map(fn ($item) => $item['fields'], $fields))
                        ]
                    ],
                    'args'       => [
                        'updateRoute' => [
                            'name'       => 'grp.models.shop.prospect.mailshot.update',
                            'parameters' => [
                                'mailshot' => $mailshot->id
                            ]

                        ],
                    ]
                ],

            ]
        );
    }

    public function authorize(ActionRequest $request): bool
    {
        return $request->user()->authTo("crm.{$this->shop->id}.edit");
    }

    /**
     * @throws Exception
     */
    public function asController(Organisation $organisation, Shop $shop, Mailshot $mailshot, ActionRequest $request): Response
    {
        $this->initialisationFromShop($shop, $request);

        return $this->handle($mailshot, $request);
    }

    public function getBreadcrumbs(Mailshot $mailshot, string $routeName, array $routeParameters): array
    {
        return ShowProspectMailshot::make()->getBreadcrumbs(
            mailshot: $mailshot,
            routeName: preg_replace('/edit$/', 'show', $routeName),
            routeParameters: $routeParameters,
            suffix: '(' . __('Editing') . ')'
        );
    }
}
