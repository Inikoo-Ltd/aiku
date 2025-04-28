<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 25 May 2023 21:14:38 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Actions\Fulfilment\Pallet\UI;

use App\Actions\Fulfilment\Fulfilment\UI\ShowFulfilment;
use App\Actions\Fulfilment\FulfilmentCustomer\ShowFulfilmentCustomer;
use App\Actions\Fulfilment\StoredItem\UI\IndexPalletStoredItems;
use App\Actions\Fulfilment\StoredItem\UI\IndexStoredItemMovements;
use App\Actions\Fulfilment\WithFulfilmentCustomerSubNavigation;
use App\Actions\Helpers\History\UI\IndexHistory;
use App\Actions\OrgAction;
use App\Actions\UI\Fulfilment\ShowWarehouseFulfilmentDashboard;
use App\Enums\Fulfilment\Pallet\PalletStateEnum;
use App\Enums\Fulfilment\Pallet\PalletStatusEnum;
use App\Enums\Fulfilment\PalletStoredItem\PalletStoredItemStateEnum;
use App\Enums\Fulfilment\StoredItemAudit\StoredItemAuditStateEnum;
use App\Enums\UI\Fulfilment\PalletTabsEnum;
use App\Http\Resources\Fulfilment\PalletResource;
use App\Http\Resources\Fulfilment\PalletStoredItemsResource;
use App\Http\Resources\Fulfilment\StoredItemMovementsResource;
use App\Http\Resources\History\HistoryResource;
use App\Models\CRM\Customer;
use App\Models\Fulfilment\Fulfilment;
use App\Models\Fulfilment\FulfilmentCustomer;
use App\Models\Fulfilment\Pallet;
use App\Models\Fulfilment\PalletDelivery;
use App\Models\Inventory\Location;
use App\Models\Inventory\Warehouse;
use App\Models\SysAdmin\Organisation;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;

/**
 * @property Pallet $pallet
 */
class ShowPallet extends OrgAction
{
    use WithFulfilmentCustomerSubNavigation;

    public Customer|null $customer = null;
    private Warehouse|Organisation|FulfilmentCustomer|Fulfilment $parent;

    public function authorize(ActionRequest $request): bool
    {
        if ($this->parent instanceof FulfilmentCustomer) {
            $this->isSupervisor = $request->user()->authTo([
                "supervisor-fulfilment-shop.".$this->fulfilment->id
            ]);

            $this->canEdit = $request->user()->authTo("fulfilment.{$this->fulfilment->id}.stored-items.edit");

            return $request->user()->authTo("fulfilment.{$this->fulfilment->id}.stored-items.view");
        } elseif ($this->parent instanceof Warehouse) {
            $this->canEdit = $request->user()->authTo("fulfilment.{$this->warehouse->id}.stored-items.edit");

            return $request->user()->authTo("fulfilment.{$this->warehouse->id}.stored-items.view");
        }

        $this->canEdit = $request->user()->authTo("fulfilment.{$this->organisation->id}.stored-items.edit");

        return $request->user()->authTo("fulfilment.{$this->organisation->id}.stored-items.view");
    }

    /** @noinspection PhpUnusedParameterInspection */
    public function inWarehouse(Organisation $organisation, Warehouse $warehouse, Pallet $pallet, ActionRequest $request): Pallet
    {
        $this->parent = $warehouse;
        $this->initialisationFromWarehouse($warehouse, $request)->withTab(PalletTabsEnum::values());

        return $this->handle($pallet);
    }

    /** @noinspection PhpUnusedParameterInspection */
    public function inLocation(Organisation $organisation, Warehouse $warehouse, Location $location, Pallet $pallet, ActionRequest $request): Pallet
    {
        $this->parent = $warehouse;
        $this->initialisationFromWarehouse($warehouse, $request)->withTab(PalletTabsEnum::values());

        return $this->handle($pallet);
    }

    public function asController(Organisation $organisation, Warehouse $warehouse, Fulfilment $fulfilment, Pallet $pallet, ActionRequest $request): Pallet
    {
        $this->parent = $organisation;
        $this->initialisation($organisation, $request)->withTab(PalletTabsEnum::values());

        return $this->handle($pallet);
    }

    /** @noinspection PhpUnusedParameterInspection */
    public function inFulfilment(Organisation $organisation, Fulfilment $fulfilment, Pallet $pallet, ActionRequest $request): Pallet
    {
        $this->parent = $fulfilment;
        $this->initialisationFromFulfilment($fulfilment, $request)->withTab(PalletTabsEnum::values());

        return $this->handle($pallet);
    }

    /** @noinspection PhpUnusedParameterInspection */
    public function inFulfilmentCustomer(Organisation $organisation, Fulfilment $fulfilment, FulfilmentCustomer $fulfilmentCustomer, PalletDelivery $palletDelivery, Pallet $pallet, ActionRequest $request): Pallet
    {
        $this->parent = $fulfilmentCustomer;
        $this->initialisationFromFulfilment($fulfilment, $request)->withTab(PalletTabsEnum::values());

        return $this->handle($pallet);
    }

    public function handle(Pallet $pallet): Pallet
    {
        return $pallet;
    }


    public function htmlResponse(Pallet $pallet, ActionRequest $request): Response
    {
        $continuePalletAuditLabel = __("Continue pallet's SKUs audit");
        $startPalletAuditLabel    = __("Start pallet's SKUs audit");

        $icon       = [
            'icon'    => ['fal', 'fa-pallet'],
            'tooltip' => __('Pallet')
        ];
        $model      = __('Pallet');
        $title      = $this->pallet->reference;
        $iconRight  = $pallet->status->statusIcon()[$pallet->status->value];
        $afterTitle = [];
        $actions    = [];
        if ($this->pallet->customer_reference) {
            $afterTitle = [
                'label' => '('.$this->pallet->customer_reference.')'
            ];
        }

        if ($pallet->state == PalletStateEnum::STORING) {
            if ($this->parent instanceof FulfilmentCustomer || $this->parent instanceof Fulfilment) {
                $actions[] = $this->isSupervisor
                    ? [
                        'supervisor' => true,
                        'type'       => 'button',
                        'style'      => 'red_outline',
                        'tooltip'    => __('delete'),
                        'icon'       => 'fal fa-trash-alt',
                        'key'        => 'delete_booked_in',
                        'ask_why'    => true,
                        'route'      => [
                            'method'     => 'delete',
                            'name'       => 'grp.models.pallet.stored.delete',
                            'parameters' => [
                                'pallet' => $pallet->id
                            ]
                        ]
                    ]
                    : [
                        'supervisor'        => false,
                        'supervisors_route' => [
                            'method'     => 'get',
                            'name'       => 'grp.json.fulfilment.supervisors.index',
                            'parameters' => [
                                'fulfilment' => $this->fulfilment->slug
                            ]
                        ],
                        'type'              => 'button',
                        'style'             => 'red_outline',
                        'tooltip'           => __('Delete'),
                        'icon'              => 'fal fa-trash-alt',
                        'key'               => 'delete_booked_in',
                        'ask_why'           => true,
                        'route'             => [
                            'method'     => 'delete',
                            'name'       => 'grp.models.pallet.stored.delete',
                            'parameters' => [
                                'pallet' => $pallet->id
                            ]
                        ]
                    ];
            } else {
                $actions[] = [
                    'type'    => 'button',
                    'style'   => 'red_outline',
                    'tooltip' => __('delete'),
                    'icon'    => 'fal fa-trash-alt',
                    'key'     => 'delete_booked_in',
                    'ask_why' => true,
                    'route'   => [
                        'method'     => 'delete',
                        'name'       => 'grp.models.pallet.stored.delete',
                        'parameters' => [
                            'pallet' => $pallet->id
                        ]
                    ]
                ];
            }
        }

        if ($this->parent instanceof FulfilmentCustomer) {
            $icon                = [
                'icon'    => ['fal', 'fa-user'],
                'tooltip' => __('Customer')
            ];
            $model               = $this->parent->customer->name;
            $openStoredItemAudit = $pallet->storedItemAudits()->where('state', StoredItemAuditStateEnum::IN_PROCESS)->first();


            if ($openStoredItemAudit) {
                $actions[] = [
                    'type'    => 'button',
                    'style'   => 'secondary',
                    'tooltip' => $continuePalletAuditLabel,
                    'label'   => $continuePalletAuditLabel,
                    'route'   => [
                        'name'       => 'grp.org.fulfilments.show.crm.customers.show.pallets.stored-item-audits.show',
                        'parameters' => array_merge($request->route()->originalParameters(), ['storedItemAudit' => $openStoredItemAudit->slug])
                    ]
                ];
            } else {
                $actions[] = [
                    'type'    => 'button',
                    'tooltip' => $startPalletAuditLabel,
                    'label'   => $startPalletAuditLabel,
                    'route'   => [
                        'name'       => 'grp.org.fulfilments.show.crm.customers.show.pallets.stored-item-audits.create',
                        'parameters' => $request->route()->originalParameters()
                    ]
                ];
            }


            if ($pallet->palletStoredItems->every(fn ($item) => $item->state == PalletStoredItemStateEnum::RETURNED) && $pallet->status != PalletStatusEnum::RETURNED) {
                $actions[] = [
                    'type'    => 'button',
                    'tooltip' => __("Return Pallet"),
                    'label'   => __("Return Pallet"),
                    'key'     => 'return-pallet',
                    'route'   => [
                        'name'       => 'grp.models.pallet.return',
                        'parameters' => [
                            'pallet' => $pallet->id
                        ],
                        'method'     => 'patch'
                    ]
                ];
            }
        }


        $subNavigation = [];
        $navigation    = PalletTabsEnum::navigation($pallet);

        if (!$pallet->fulfilmentCustomer->items_storage) {
            unset($navigation[PalletTabsEnum::STORED_ITEMS->value]);
        }

        if ($this->parent instanceof FulfilmentCustomer) {
            $subNavigation = $this->getFulfilmentCustomerSubNavigation($this->parent, $request);
        }

        $routeName = null;
        if ($this->parent instanceof Warehouse) {
            $routeName           = 'grp.org.warehouses.show.inventory.pallets.current.edit';
            $openStoredItemAudit = $pallet->storedItemAudits()->where('state', StoredItemAuditStateEnum::IN_PROCESS)->first();

            if ($openStoredItemAudit) {
                $actions[] = [
                    'type'    => 'button',
                    'style'   => 'secondary',
                    'tooltip' => $continuePalletAuditLabel,
                    'label'   => $continuePalletAuditLabel,
                    'route'   => [
                        'name'       => 'grp.org.warehouses.show.inventory.pallets.show.stored-item-audit.show',
                        'parameters' => array_merge($request->route()->originalParameters(), ['storedItemAudit' => $openStoredItemAudit->slug])
                    ]
                ];
            } else {
                $actions[] = [
                    'type'    => 'button',
                    'tooltip' => $startPalletAuditLabel,
                    'label'   => $startPalletAuditLabel,
                    'route'   => [
                        'name'       => 'grp.org.warehouses.show.inventory.pallets.show.stored-item-audit.create',
                        'parameters' => $request->route()->originalParameters()
                    ]
                ];
            }
        } elseif ($this->parent instanceof Fulfilment) {
            $routeName = 'grp.org.fulfilments.show.operations.pallets.current.edit';
        } elseif ($this->parent instanceof FulfilmentCustomer) {
            $routeName = 'grp.org.fulfilments.show.crm.customers.show.pallets.edit';
        }
        $actions[] = [
            'type'    => 'button',
            'style'   => 'edit',
            'tooltip' => __('edit pallet'),
            'route'   => [
                'name'       => $routeName,
                'parameters' => array_values(request()->route()->originalParameters())
            ]
        ];

        if ($this->parent instanceof FulfilmentCustomer) {
            $actions[] = [
                'type'   => 'button',
                'style'  => 'tertiary',
                'label'  => 'PDF Label',
                'target' => '_blank',
                'icon'   => 'fal fa-file-pdf',
                'key'    => 'action',
                'route'  => [
                    'name'       => 'grp.org.fulfilments.show.crm.customers.show.pallets.export',
                    'parameters' => [
                        ...array_values(request()->route()->originalParameters()),
                        [
                            'type' => 'pdf'
                        ]
                    ],
                ]
            ];
        }

        $storedItemsList = array_map(function ($palletStoredItem) {
            return [
                'name'      => $palletStoredItem->storedItem->name,
                'reference' => $palletStoredItem->storedItem->reference,
                'quantity'  => (int)$palletStoredItem->quantity,
                'state'     => $palletStoredItem->state
            ];
        }, $pallet->palletStoredItems->all());

        return Inertia::render(
            'Org/Fulfilment/Pallet',
            [
                'title'       => __('pallets'),
                'breadcrumbs' => $this->getBreadcrumbs(
                    $this->parent,
                    request()->route()->originalParameters()
                ),
                'navigation'  => [
                    'previous' => $this->getPrevious($pallet, $request),
                    'next'     => $this->getNext($pallet, $request),
                ],
                'pageHead'    => [
                    'icon'          => $icon,
                    'title'         => $title,
                    'model'         => $model,
                    'iconRight'     => $iconRight,
                    'noCapitalise'  => true,
                    'afterTitle'    => $afterTitle,
                    'subNavigation' => $subNavigation,
                    'actions'       => $actions
                ],
                'tabs'        => [
                    'current'    => $this->tab,
                    'navigation' => $navigation,
                ],

                'pallet'            => PalletResource::make($pallet),
                'list_stored_items' => $storedItemsList,

                PalletTabsEnum::SHOWCASE->value => $this->tab == PalletTabsEnum::SHOWCASE->value ?
                    fn () => PalletResource::make($pallet) : Inertia::lazy(fn () => PalletResource::make($pallet)),

                PalletTabsEnum::STORED_ITEMS->value => $this->tab == PalletTabsEnum::STORED_ITEMS->value ?
                    fn () => PalletStoredItemsResource::collection(IndexPalletStoredItems::run($pallet, PalletTabsEnum::STORED_ITEMS->value))
                    : Inertia::lazy(fn () => PalletStoredItemsResource::collection(IndexPalletStoredItems::run($pallet, PalletTabsEnum::STORED_ITEMS->value))),

                PalletTabsEnum::MOVEMENTS->value => $this->tab == PalletTabsEnum::MOVEMENTS->value ?
                    fn () => StoredItemMovementsResource::collection(IndexStoredItemMovements::run($pallet, PalletTabsEnum::MOVEMENTS->value))
                    : Inertia::lazy(fn () => StoredItemMovementsResource::collection(IndexStoredItemMovements::run($pallet, PalletTabsEnum::MOVEMENTS->value))),

                PalletTabsEnum::HISTORY->value => $this->tab == PalletTabsEnum::HISTORY->value ?
                    fn () => HistoryResource::collection(IndexHistory::run($this->pallet))
                    : Inertia::lazy(fn () => HistoryResource::collection(IndexHistory::run($this->pallet)))

            ]
        )->table(IndexHistory::make()->tableStructure(prefix: PalletTabsEnum::HISTORY->value))
            ->table(IndexStoredItemMovements::make()->tableStructure($pallet, prefix: PalletTabsEnum::MOVEMENTS->value))
            ->table(IndexPalletStoredItems::make()->tableStructure($pallet, prefix: PalletTabsEnum::STORED_ITEMS->value));
    }


    public function jsonResponse(Pallet $pallet): PalletResource
    {
        return new PalletResource($pallet);
    }

    public function getBreadcrumbs(Organisation|Warehouse|Fulfilment|FulfilmentCustomer $parent, array $routeParameters, string $suffix = ''): array
    {
        $pallet = Pallet::where('slug', $routeParameters['pallet'])->first();

        return match (class_basename($parent)) {
            'Warehouse' => $this->getBreadcrumbsFromWarehouse($pallet, $suffix),
            'Organisation', 'Fulfilment' => $this->getBreadcrumbsFromFulfilment($pallet, $suffix),
            default => $this->getBreadcrumbsFromFulfilmentCustomer($pallet, $suffix),
        };
    }

    public function getBreadcrumbsFromWarehouse(Pallet $pallet, $suffix = null): array
    {
        return array_merge(
            ShowWarehouseFulfilmentDashboard::make()->getBreadcrumbs(request()->route()->originalParameters()),
            [
                [
                    'type'           => 'modelWithIndex',
                    'modelWithIndex' => [
                        'index' => [
                            'route' => [
                                'name'       => 'grp.org.warehouses.show.inventory.pallets.current.index',
                                'parameters' => array_values(request()->route()->originalParameters())
                            ],
                            'label' => __('Pallet')
                        ],
                        'model' => [
                            'route' => [
                                'name'       => 'grp.org.warehouses.show.inventory.pallets.current.show',
                                'parameters' => array_values(request()->route()->originalParameters())
                            ],
                            'label' => $pallet->reference,
                        ],
                    ],
                    'suffix'         => $suffix,
                ],
            ]
        );
    }

    public function getBreadcrumbsFromFulfilment(Pallet $pallet, $suffix = null): array
    {
        return array_merge(
            ShowFulfilment::make()->getBreadcrumbs(request()->route()->originalParameters()),
            [
                [
                    'type'           => 'modelWithIndex',
                    'modelWithIndex' => [
                        'index' => [
                            'route' => [
                                'name'       => 'grp.org.fulfilments.show.operations.pallets.current.index',
                                'parameters' => array_values(request()->route()->originalParameters())
                            ],
                            'label' => __('Pallets')
                        ],
                        'model' => [
                            'route' => [
                                'name'       => 'grp.org.fulfilments.show.operations.pallets.current.show',
                                'parameters' => array_values(request()->route()->originalParameters())
                            ],
                            'label' => $pallet->reference,
                        ],
                    ],
                    'suffix'         => $suffix,
                ],
            ]
        );
    }

    public function getBreadcrumbsFromFulfilmentCustomer(Pallet $pallet, $suffix = null): array
    {
        return array_merge(
            ShowFulfilmentCustomer::make()->getBreadcrumbs(request()->route()->originalParameters()),
            [
                [
                    'type'           => 'modelWithIndex',
                    'modelWithIndex' => [
                        'index' => [
                            'route' => [
                                'name'       => 'grp.org.fulfilments.show.crm.customers.show.pallets.index',
                                'parameters' => array_values(request()->route()->originalParameters())
                            ],
                            'label' => __('Pallets')
                        ],
                        'model' => [
                            'route' => [
                                'name'       => 'grp.org.fulfilments.show.crm.customers.show.pallets.show',
                                'parameters' => array_values(request()->route()->originalParameters())
                            ],
                            'label' => $pallet->reference,
                        ],
                    ],
                    'suffix'         => $suffix,
                ],
            ]
        );
    }

    public function getPrevious(Pallet $pallet, ActionRequest $request): ?array
    {
        $previous = Pallet::where('id', '<', $pallet->id)
            ->where('fulfilment_customer_id', $this->parent->id)
            ->whereNotNull('slug')->orderBy('id', 'desc')->first();

        return $this->getNavigation($previous, $request->route()->getName());
    }

    public function getNext(Pallet $pallet, ActionRequest $request): ?array
    {
        $next = Pallet::where('id', '>', $pallet->id)
            ->where('fulfilment_customer_id', $this->parent->id)
            ->whereNotNull('slug')->orderBy('id')->first();

        return $this->getNavigation($next, $request->route()->getName());
    }

    private function getNavigation(?Pallet $pallet, string $routeName): ?array
    {
        if (!$pallet) {
            return null;
        }

        return match ($routeName) {
            'grp.org.fulfilments.show.crm.customers.show.pallets.show' => [
                'label' => $pallet->slug,
                'route' => [
                    'name'       => $routeName,
                    'parameters' => [
                        'organisation'       => $pallet->organisation->slug,
                        'fulfilment'         => $pallet->fulfilment->slug,
                        'fulfilmentCustomer' => $pallet->fulfilmentCustomer->slug,
                        'pallet'             => $pallet->slug
                    ]
                ]
            ],
            'grp.org.warehouses.show.inventory.pallets.current.show' => [
                'label' => $pallet->slug,
                'route' => [
                    'name'       => $routeName,
                    'parameters' => [
                        'organisation' => $pallet->organisation->slug,
                        'warehouse'    => $pallet->warehouse->slug,
                        'pallet'       => $pallet->slug
                    ]
                ]
            ],

            'grp.org.fulfilments.show.operations.pallets.current.show' => [
                'label' => $pallet->reference,
                'route' => [
                    'name'       => $routeName,
                    'parameters' => [
                        'organisation' => $pallet->organisation->slug,
                        'fulfilment'   => $pallet->fulfilment->slug,
                        'pallet'       => $pallet->slug
                    ]

                ]
            ],

            // 'grp.org.fulfilments.show.crm.customers.show.invoices.show'=> [
            //     'label'=> $invoice->reference,
            //     'route'=> [
            //         'name'      => $routeName,
            //         'parameters'=> [
            //             'organisation'       => $invoice->organisation->slug,
            //             'fulfilment'         => $this->parent->slug,
            //             'fulfilmentCustomer' => $this->parent->slug,
            //             'invoice'            => $invoice->slug
            //         ]

            //     ]
            // ],
            default => null,
        };
    }
}
