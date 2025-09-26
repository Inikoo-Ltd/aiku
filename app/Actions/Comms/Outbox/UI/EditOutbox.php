<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 19 Nov 2024 11:09:35 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Comms\Outbox\UI;

use App\Actions\OrgAction;
use App\Enums\UI\Mail\OutboxTabsEnum;
use App\Models\Catalogue\Shop;
use App\Models\Comms\Outbox;
use App\Models\Fulfilment\Fulfilment;
use App\Models\SysAdmin\Organisation;
use Exception;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;

class EditOutbox extends OrgAction
{
    private Fulfilment|Shop $parent;

    /**
     * @throws Exception
     */
    public function handle(Outbox $outbox, ActionRequest $request): Response
    {
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
                            'name' => 'grp.models.fulfilment.outboxes.update',
                            'parameters' => [
                                'fulfilment' => $outbox->fulfilment->id,
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
        if ($this->parent instanceof Fulfilment) {
            return $this->canEdit = $request->user()->authTo("fulfilment-shop.{$this->fulfilment->id}.edit");
        }

        return $request->user()->authTo([
            'shop-admin.' . $this->shop->id,
            'marketing.' . $this->shop->id . '.view',
            'web.' . $this->shop->id . '.view',
            'orders.' . $this->shop->id . '.view',
            'crm.' . $this->shop->id . '.view',
        ]);
    }

    /** @noinspection PhpUnusedParameterInspection */
    /**
     * @throws \Exception
     */
    public function inFulfilment(Organisation $organisation, Fulfilment $fulfilment, Outbox $outbox, ActionRequest $request): Response
    {
        $this->parent = $fulfilment;
        $this->initialisationFromFulfilment($fulfilment, $request)->withTab(OutboxTabsEnum::values());

        return $this->handle($outbox, $request);
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

}
