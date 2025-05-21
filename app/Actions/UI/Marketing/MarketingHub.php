<?php

/*
 * Author: Jonathan Lopez Sanchez <jonathan@ancientwisdom.biz>
 * Created: Fri, 17 Mar 2023 10:36:00 Central European Standard Time, Malaga, Spain
 * Copyright (c) 2023, Inikoo LTD
 */

namespace App\Actions\UI\Marketing;

use App\Actions\InertiaAction;
use App\Actions\UI\Dashboards\ShowGroupDashboard;
use App\Models\Catalogue\Shop;
use App\Models\Comms\Outbox;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;

/**
 * @property Outbox $outbox
 * @property \App\Models\SysAdmin\User $user
 */
class MarketingHub extends InertiaAction
{
    public function authorize(ActionRequest $request): bool
    {
        return $request->user()->authTo('marketing.view');
    }


    public function inOrganisation(ActionRequest $request): ActionRequest
    {
        $this->initialisation($request);

        return $request;
    }

    /** @noinspection PhpUnusedParameterInspection */
    public function inShop(Shop $shop, ActionRequest $request): ActionRequest
    {
        $this->initialisation($request);

        return $request;
    }


    public function htmlResponse(ActionRequest $request): Response
    {
        $routeName       = $request->route()->getName();
        $routeParameters = $request->route()->originalParameters();


        return Inertia::render(
            'Comms/MailHub',
            [
                'breadcrumbs' => $this->getBreadcrumbs(
                    $routeName,
                    $routeParameters
                ),
                'title'       => __('marketing'),
                'pageHead'    => [
                    'title' => __('marketing'),
                ],


                'flatTreeMaps' => match ($routeName) {
                    'shops.show.mail.hub' =>
                    [
                        [
                            [
                                'name'  => __('post room'),
                                'icon'  => ['fal', 'fa-mailbox'],
                                'route'  => ['grp.marketing.post_rooms.index'],
                                'index' => [
                                    'number' => $routeParameters['shop']
                                ]

                            ],
                            [
                                'name'  => __('outboxes'),
                                'icon'  => ['fal', 'fa-inbox-out'],
                                'route'  => ['grp.marketing.outboxes.index'],
                                'index' => [
                                    'number' => $this->outbox
                                ]

                            ],
                            [
                                'name'  => __('mailshots'),
                                'icon'  => ['fal', 'fa-mail-bulk'],
                                'route'  => ['grp.marketing.mailshots.index'],
                                'index' => [
                                    'number' => $this->outbox

                                ]

                            ],
                            [
                                'name'  => __('dispatched emails'),
                                'icon'  => ['fal', 'fa-envelope'],
                                'route'  => ['grp.marketing.dispatched-emails.index'],
                                'index' => [
                                    'number' => $this->outbox
                                ]

                            ],
                        ]


                    ],
                    default => [
                        [
                            [
                                'name'  => __('post room'),
                                'icon'  => ['fal', 'fa-mailbox'],
                                'route'  => ['grp.marketing.post_rooms.index'],
                                'index' => [
                                    'number' => $this->outbox
                                ]

                            ],
                            [
                                'name'  => __('outboxes'),
                                'icon'  => ['fal', 'fa-inbox-out'],
                                'route'  => ['grp.marketing.outboxes.index'],
                                'index' => [
                                    'number' => $this->outbox
                                ]

                            ],
                            [
                                'name'  => __('mailshots'),
                                'icon'  => ['fal', 'fa-mail-bulk'],
                                'route'  => ['grp.marketing.mailshots.index'],
                                'index' => [
                                    'number' => $this->outbox

                                ]

                            ],
                            [
                                'name'  => __('dispatched emails'),
                                'icon'  => ['fal', 'fa-envelope'],
                                'route'  => ['grp.marketing.dispatched-emails.index'],
                                'index' => [
                                    'number' => $this->outbox
                                ]

                            ],
                        ]


                    ]
                }


            ]
        );
    }

    public function getBreadcrumbs($routeName, $routeParameters): array
    {
        return match ($routeName) {
            'grp.marketing.hub' => array_merge(
                ShowGroupDashboard::make()->getBreadcrumbs(),
                [
                    [
                        'type'   => 'simple',
                        'simple' => [
                            'route' => [
                                'name' => 'grp.org.shops.show.marketing.dashboard'
                            ],
                            'label' => __('Marketing').' ('.__('all shops').')',
                            'icon'  => 'fal fa-bullhorn'
                        ],
                    ],
                ]
            ),
            'grp.marketing.shops.show.hub', 'grp.org.shops.show.marketing.mailshots.index', 'grp.org.shops.show.marketing.newsletters.index' =>
            array_merge(
                ShowGroupDashboard::make()->getBreadcrumbs(),
                [
                    [
                        'type'   => 'simple',
                        'simple' => [
                            'route' => [
                                'name'       => 'grp.org.shops.show.marketing.dashboard',
                                'parameters' => $routeParameters
                            ],
                            'label' => __('Marketing').' ('.$routeParameters['shop'].')',
                            'icon'  => 'fal fa-bullhorn'
                        ],
                    ],
                ]
            ),
            default => []
        };
    }
}
