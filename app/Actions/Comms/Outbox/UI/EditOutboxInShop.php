<?php

/*
 * Author: Eka yudinatha<Ekayudinatha@gmail.com>
 * Created: Tue, 19 Dec 2025 11:09:35 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2025, Eka yudinatha
 */

namespace App\Actions\Comms\Outbox\UI;

use App\Actions\OrgAction;
use App\Enums\Comms\Outbox\OutboxCodeEnum;
use App\Enums\UI\Mail\OutboxTabsEnum;
use App\Models\Catalogue\Shop;
use App\Models\Comms\Outbox;
use App\Models\SysAdmin\Organisation;
use Exception;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;

class EditOutboxInShop extends OrgAction
{
    private Shop $parent;

    /**
     * @throws Exception
     */
    public function handle(Outbox $outbox, ActionRequest $request): Response
    {

        $fields = [];

        $subjectField = [
            'title' => '',
            'fields' => [
                'subject' => [
                    'type' => 'input',
                    'label' => __('subject'),
                    'placeholder' => __('Email subject'),
                    'required' => false,
                    'value' => $outbox->emailOngoingRun?->email?->subject,
                ],
            ]
        ];

        $intervalField = [
            'title' => '',
            'fields' => [
                'interval' => [
                    'type' => 'input_number',
                    'label' => __('Cooldown Period (in hours)'),
                    'placeholder' => __('Cooldown Period (in hours)'),
                    'required' => true,
                    'value' => $outbox->interval,
                ],
            ]
        ];

        $isApplicableField = [
            'title' => '',
            'fields' => [
                'is_applicable' => [
                    'type' => 'select',
                    'label' => __('Notification active'),
                    'placeholder' => __('Notification active'),
                    'options' => $outbox->is_applicable ? [
                        ['label' => __('Yes'), 'value' => true],
                        ['label' => __('No'), 'value' => false],
                    ] : [
                        ['label' => __('No'), 'value' => false],
                        ['label' => __('Yes'), 'value' => true],
                    ],
                    'required' => true,
                    'mode' => 'single',
                    'value' => $outbox->is_applicable,
                ],
            ]
        ];

        if (in_array($outbox->code, [OutboxCodeEnum::REORDER_REMINDER, OutboxCodeEnum::REORDER_REMINDER_2ND, OutboxCodeEnum::REORDER_REMINDER_3RD])) {
            $fields[] = [
                'title' => '',
                'fields' => [
                    'days_after' => [
                        'type' => 'input_number',
                        'label' => __('Days after last order dispatched'),
                        'placeholder' => __('Days after last order dispatched'),
                        'required' => false,
                        'value' => $outbox->days_after,
                    ],
                ]
            ];
        } elseif (in_array($outbox->code, [OutboxCodeEnum::BASKET_LOW_STOCK])) {
            $fields[] = $subjectField;
            $fields[] = [
                'title' => '',
                'fields' => [
                    'threshold' => [
                        'type' => 'input_number',
                        'label' => __('Low Stock Threshold'),
                        'placeholder' => __('Low Stock Threshold'),
                        'required' => true,
                        'value' => $outbox->threshold,
                    ],
                ]
            ];
            $fields[] = $intervalField;
            $fields[] = $isApplicableField;
        } elseif (in_array($outbox->code, [OutboxCodeEnum::OOS_IN_ORDER_NOTIFICATION])) {
            $fields[] = $subjectField;
            $fields[] = $intervalField;
            $fields[] = $isApplicableField;
        } else {
            $fields[] = $subjectField;
        }



        return Inertia::render(
            'EditModel',
            [
                'breadcrumbs' => $this->getBreadcrumbs(
                    $request->route()->getName(),
                    $request->route()->originalParameters()
                ),
                'title' => __('Edit outbox'),
                'pageHead' => [
                    'title' => __('Edit outbox'),
                    'actions' => [
                        [
                            'type' => 'button',
                            'style' => 'exitEdit',
                            'route' => [
                                'name' => preg_replace('/edit/', 'show', $request->route()->getName()),
                                'parameters' => array_values($request->route()->originalParameters())
                            ]
                        ]
                    ],
                ],
                'formData' => [
                    'blueprint' =>
                    [
                        [
                            "label"   => __("Settings"),
                            "icon"    => "fa-light fa-sliders-h",
                            'fields' => array_merge(...array_map(fn ($item) => $item['fields'], $fields))
                        ]
                    ],
                    'args' => [
                        'updateRoute' => [
                            'name' => 'grp.models.shop.outboxes.update',
                            'parameters' => [
                                'shop' => $outbox->shop->id,
                                'outbox' => $outbox->id
                            ]
                        ],
                    ]
                ],

            ]
        );
    }

    public function authorize(ActionRequest $request): bool
    {
        return true;

        if ($this->parent instanceof Shop) {
            return $this->canEdit = $request->user()->authTo("shop.{$this->shop->id}.edit");
        }

        return $request->user()->authTo([
            'shop-admin.' . $this->shop->id,
            'marketing.' . $this->shop->id . '.view',
            'web.' . $this->shop->id . '.view',
            'orders.' . $this->shop->id . '.view',
            'crm.' . $this->shop->id . '.view',
        ]);
    }


    public function getBreadcrumbs(string $routeName, array $routeParameters, $suffix = null): array
    {
        $headCrumb = function (Outbox $outbox, array $routeParameters, $suffix = null) {
            return [
                [
                    'type' => 'simple',
                    'simple' => [
                        'route' => $routeParameters,
                        'label' => $outbox->name,
                    ],
                    'suffix' => $suffix . __('(editing)')
                ],
            ];
        };

        $outbox = Outbox::where('slug', $routeParameters['outbox'])->first();

        return match ($routeName) {
            'grp.org.shops.show.dashboard.comms.outboxes.show',
            'grp.org.shops.show.dashboard.comms.outboxes.edit',
            'grp.org.shops.show.dashboard.comms.outboxes.workshop' =>
            array_merge(
                IndexOutboxes::make()->getBreadcrumbs('grp.org.shops.show.dashboard.comms.outboxes.index', $routeParameters),
                $headCrumb(
                    $outbox,
                    [

                        'name' => 'grp.org.shops.show.dashboard.comms.outboxes.show',
                        'parameters' => $routeParameters

                    ],
                    $suffix
                )
            ),
            'grp.org.shops.show.web.websites.outboxes.show' =>
            array_merge(
                IndexOutboxes::make()->getBreadcrumbs('grp.org.shops.show.web.websites.outboxes', $routeParameters),
                $headCrumb(
                    $outbox,
                    [

                        'name' => 'grp.org.shops.show.web.websites.outboxes.show',
                        'parameters' => $routeParameters

                    ],
                    $suffix
                )
            ),
            'grp.org.fulfilments.show.operations.comms.outboxes.show',
            'grp.org.fulfilments.show.operations.comms.outboxes.edit' =>
            array_merge(
                IndexOutboxes::make()->getBreadcrumbs('grp.org.fulfilments.show.operations.comms.outboxes', $routeParameters),
                $headCrumb(
                    $outbox,
                    [

                        'name' => 'grp.org.fulfilments.show.operations.comms.outboxes.show',
                        'parameters' => $routeParameters

                    ],
                    $suffix
                )
            ),
            'grp.org.fulfilments.show.operations.comms.outboxes.dispatched-email.show' =>
            array_merge(
                IndexOutboxes::make()->getBreadcrumbs($routeName, $routeParameters),
                [
                    [
                        'type' => 'simple',
                        'simple' => [
                            'route' => [
                                'name' => 'grp.org.fulfilments.show.operations.comms.outboxes.show',
                                'parameters' => array_merge($routeParameters, [
                                    'tab' => 'dispatched_emails'
                                ])
                            ],
                            'label' => __($outbox->name)
                        ]
                    ]
                ]
            ),
            'grp.org.shops.show.dashboard.comms.outboxes.dispatched-email.show' =>
            array_merge(
                IndexOutboxes::make()->getBreadcrumbs($routeName, $routeParameters),
                [
                    [
                        'type' => 'simple',
                        'simple' => [
                            'route' => [
                                'name' => 'grp.org.shops.show.dashboard.comms.outboxes.show',
                                'parameters' => array_merge($routeParameters, [
                                    'tab' => 'dispatched_emails'
                                ])
                            ],
                            'label' => __($outbox->name)
                        ]
                    ]
                ]
            ),
            default => []
        };
    }

    public function getPrevious(Outbox $outbox, ActionRequest $request): ?array
    {
        $previous = Outbox::where('slug', '<', $outbox->slug)->orderBy('slug', 'desc')->first();

        return $this->getNavigation($previous, $request->route()->getName());
    }

    public function getNext(Outbox $outbox, ActionRequest $request): ?array
    {
        $next = Outbox::where('slug', '>', $outbox->slug)->orderBy('slug')->first();

        return $this->getNavigation($next, $request->route()->getName());
    }

    private function getNavigation(?Outbox $outbox, string $routeName): ?array
    {
        if (!$outbox) {
            return null;
        }
        return match ($routeName) {
            'grp.org.shops.show.dashboard.comms.outboxes.show',
            'grp.org.shops.show.dashboard.comms.outboxes.edit' => [
                'label' => $outbox->name,
                'route' => [
                    'name' => $routeName,
                    'parameters' => [
                        'organisation' => $this->organisation->slug,
                        'shop' => $outbox->shop->slug,
                        'outbox' => $outbox->slug
                    ]

                ]
            ],
            'grp.org.shops.show.web.websites.outboxes.show' => [
                'label' => $outbox->name,
                'route' => [
                    'name' => $routeName,
                    'parameters' => [
                        'organisation' => $this->organisation->slug,
                        'shop' => $outbox->shop->slug,
                        'website' => $outbox->website->slug,
                        'outbox' => $outbox->slug
                    ]

                ]
            ],
            'grp.org.fulfilments.show.operations.comms.outboxes.show' => [
                'label' => $outbox->name,
                'route' => [
                    'name' => $routeName,
                    'parameters' => [
                        'organisation' => $this->organisation->slug,
                        'fulfilment' => $this->fulfilment->slug,
                        'outbox' => $outbox->slug
                    ]

                ]
            ],
        };
    }

    public function asController(Organisation $organisation, Shop $shop, Outbox $outbox, ActionRequest $request)
    {

        $this->parent = $shop;
        $this->initialisationFromShop($shop, $request)->withTab(OutboxTabsEnum::values());

        return $this->handle($outbox, $request);
    }
}
