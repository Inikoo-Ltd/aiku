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
        if ($outbox->code === OutboxCodeEnum::REORDER_REMINDER) {
            $fields[] = [
                'title' => '',
                'fields' => [
                    'days_after' => [
                        'type' => 'input_number',
                        'label' => __('Days after last order dispatched'),
                        'placeholder' => __('Days after last order dispatched'),
                        'required' => false,
                        'value' => $outbox->setting?->days_after,
                    ],
                    'send_time' => [
                        'type' => 'select',
                        'label' => __('Send Time'),
                        'placeholder' => __('Send Time'),
                        'options'  => [
                            ['label' => '00:00', 'value' => '00:00:00'],
                            ['label' => '01:00', 'value' => '01:00:00'],
                            ['label' => '02:00', 'value' => '02:00:00'],
                            ['label' => '03:00', 'value' => '03:00:00'],
                            ['label' => '04:00', 'value' => '04:00:00'],
                            ['label' => '05:00', 'value' => '05:00:00'],
                            ['label' => '06:00', 'value' => '06:00:00'],
                            ['label' => '07:00', 'value' => '07:00:00'],
                            ['label' => '08:00', 'value' => '08:00:00'],
                            ['label' => '09:00', 'value' => '09:00:00'],
                            ['label' => '10:00', 'value' => '10:00:00'],
                            ['label' => '11:00', 'value' => '11:00:00'],
                            ['label' => '12:00', 'value' => '12:00:00'],
                            ['label' => '13:00', 'value' => '13:00:00'],
                            ['label' => '14:00', 'value' => '14:00:00'],
                            ['label' => '15:00', 'value' => '15:00:00'],
                            ['label' => '16:00', 'value' => '16:00:00'],
                            ['label' => '17:00', 'value' => '17:00:00'],
                            ['label' => '18:00', 'value' => '18:00:00'],
                            ['label' => '19:00', 'value' => '19:00:00'],
                            ['label' => '20:00', 'value' => '20:00:00'],
                            ['label' => '21:00', 'value' => '21:00:00'],
                            ['label' => '22:00', 'value' => '22:00:00'],
                            ['label' => '23:00', 'value' => '23:00:00'],
                        ],
                        'value'    => substr($outbox->setting?->send_time ?? '', 0, 8),
                        'required' => false,
                    ],
                ]
            ];
        } else {
            $fields[] = [
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
