<?php

/*
 * author Arya Permana - Kirin
 * created on 21-02-2025-08h-27m
 * github: https://github.com/KirinZero0
 * copyright 2025
*/

namespace App\Actions\Fulfilment\StoredItemAudit\UI;

use App\Actions\Fulfilment\Pallet\UI\ShowPallet;
use App\Actions\Fulfilment\StoredItemAuditDelta\UI\IndexStoredItemAuditDeltas;
use App\Actions\Fulfilment\WithFulfilmentCustomerSubNavigation;
use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\WithFulfilmentShopAuthorisation;
use App\Enums\Fulfilment\StoredItemAudit\StoredItemAuditStateEnum;
use App\Http\Resources\Fulfilment\FulfilmentCustomerResource;
use App\Http\Resources\Fulfilment\MayaPalletStoredItemAuditResource;
use App\Http\Resources\Fulfilment\PalletResource;
use App\Http\Resources\Fulfilment\StoredItemAuditDeltasResource;
use App\Http\Resources\Fulfilment\StoredItemAuditResource;
use App\Models\Fulfilment\Fulfilment;
use App\Models\Fulfilment\FulfilmentCustomer;
use App\Models\Fulfilment\Pallet;
use App\Models\Fulfilment\StoredItemAudit;
use App\Models\Fulfilment\StoredItemAuditDelta;
use App\Models\Inventory\Location;
use App\Models\Inventory\Warehouse;
use App\Models\SysAdmin\Organisation;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;

class ShowStoredItemAuditForPallet extends OrgAction
{
    // use WithFulfilmentShopAuthorisation; //idk what to use since we have inWarehouse
    use WithFulfilmentCustomerSubNavigation;

    private Fulfilment|Location|FulfilmentCustomer|Warehouse $parent;
    private Pallet $pallet;

    private bool $selectStoredPallets = false;

    public function handle(StoredItemAudit $storedItemAudit): StoredItemAudit
    {
        return $storedItemAudit;
    }

    public function jsonResponse(StoredItemAudit $storedItemAudit, ActionRequest $request): StoredItemAuditResource|MayaPalletStoredItemAuditResource
    {
        if ($request->hasHeader('Maya-Version')) {
            return MayaPalletStoredItemAuditResource::make($storedItemAudit);
        }
        return StoredItemAuditResource::make($storedItemAudit);
    }

    public function htmlResponse(StoredItemAudit $storedItemAudit, ActionRequest $request): Response
    {
        /** @var Pallet $pallet */
        $pallet = $storedItemAudit->scope;

        $disabled      = $storedItemAudit->deltas->every(function (StoredItemAuditDelta $delta) {
            return $delta->storedItem === null;
        });
        $actions       = [];
        $subNavigation = [];
        $editDeltas    = null;
        $deltas        = null;
        if ($storedItemAudit->state === StoredItemAuditStateEnum::IN_PROCESS) {
            $actions    = [
                [
                    'type'  => 'button',
                    'style' => 'secondary',
                    'key'   => 'add-sku',
                    'label' => __('Add SKU'),
                ],
                [
                    'type'     => 'button',
                    'style'    => 'primary',
                    'label'    => __('Complete Audit'),
                    'disabled' => $disabled,
                    'route'    => [
                        'method'     => 'patch',
                        'name'       => 'grp.models.stored_item_audit.complete',
                        'parameters' => [
                            $storedItemAudit->id
                        ],
                    ]
                ]
            ];
            $editDeltas = [
                'stored_items' => $pallet->getEditStoredItemDeltasQuery($pallet->id, $storedItemAudit->id)
                ->where('pallet_stored_items.pallet_id', $pallet->id)
                ->get()->map(fn ($item) => [
                    'stored_item_audit_id'       => $storedItemAudit->id,
                    'pallet_id'                  => $item->pallet_id,
                    'stored_item_id'             => $item->stored_item_id,
                    'reference'                  => $item->stored_item_reference,
                    'quantity'                   => (int)$item->quantity,
                    'audited_quantity'           => (int)$item->audited_quantity,
                    'audit_notes'                => $item->audit_notes,
                    'stored_item_audit_delta_id' => $item->stored_item_audit_delta_id,
                    'audit_type'                 => $item->audit_type,
                    'update_routes'              => [
                        'name'       => 'grp.models.stored_item_audit_delta.update',
                        'parameters' => [
                            $item->stored_item_audit_delta_id
                        ]
                    ],
                    'type'                       => 'current_item',
                ]),

                'new_stored_items' => $pallet->getEditNewStoredItemDeltasQuery($pallet->id)
                ->where('stored_item_audit_deltas.pallet_id', $pallet->id)
                ->where('stored_item_audit_deltas.stored_item_audit_id', $storedItemAudit->id)
                    ->get()->map(fn ($item) => [
                        'stored_item_audit_id'    => $storedItemAudit->id,
                        'stored_item_id'          => $item->stored_item_id,
                        'reference'               => $item->stored_item_reference,
                        'quantity'                => 0,
                        'audited_quantity'        => (int)$item->audited_quantity,
                        'stored_item_audit_delta_id' => $item->audit_id,
                        'audit_type'                 => $item->audit_type,
                        'update_routes'           => [
                            'name'       => 'grp.models.stored_item_audit_delta.update',
                            'parameters' => [
                                $item->audit_id
                            ]
                        ],
                        'audit_notes'             => $item->audit_notes,
                        'type'                    => 'new_item'
                    ])
        ];
        } else {
            $deltas = StoredItemAuditDeltasResource::collection(IndexStoredItemAuditDeltas::run($storedItemAudit, 'stored_item_deltas'));
        }
        if ($this->parent instanceof FulfilmentCustomer) {
            $subNavigation = $this->getFulfilmentCustomerSubNavigation($this->parent, $request);
        }
        $render = Inertia::render(
            'Org/Fulfilment/PalletAudit',
            [
                'title'       => __('pallet audit'),
                'breadcrumbs' => $this->getBreadcrumbs(
                    $storedItemAudit,
                    $request->route()->getName(),
                    $request->route()->originalParameters()
                ),
                // 'navigation' => [
                //     'previous' => $this->getPrevious($palletReturn, $request),
                //     'next'     => $this->getNext($palletReturn, $request),
                // ],
                'pageHead'    => [
                    'title'         => __('Audit'),
                    'model'         => __('Pallet'),
                    'icon'          => [
                        'icon'  => ['fal', 'fa-pallet'],
                        'title' => __('Pallet'),
                    ],
                    'subNavigation' => $subNavigation,
                    'actions'       => $actions,
                ],

                'route_list' => [
                    'update'                  => [
                        'name'       => 'grp.models.stored_item_audit.update',
                        'parameters' => [
                            'storedItemAudit' => $storedItemAudit->id
                        ]
                    ],
                    'stored_item_audit_delta' => [
                        'update' => [  // Update quantity
                                        'method' => 'patch',
                                        'name'   => 'grp.models.stored_item_audit_delta.update',
                                       //parameters: add the storedItemAuditDelta id in the FE
                        ],
                        'delete' => [
                            'method' => 'delete',
                            'name'   => 'grp.models.stored_item_audit_delta.delete',
                            //parameters: add the storedItemAuditDelta id in the FE
                        ],
                        'store'  => [
                            'method'     => 'post',
                            'name'       => 'grp.models.stored_item_audit.stored_item_audit_delta.store',
                            'parameters' => [
                                $storedItemAudit->id
                            ]
                        ],
                    ]
                ],

                'storedItemsRoute' => [
                    'index'  => [
                        'name'       => 'grp.json.fulfilment-customer.audit.stored-items.index',
                        'parameters' => [
                            'fulfilmentCustomer' => $storedItemAudit->fulfilmentCustomer->slug,
                            'storedItemAudit'    => $storedItemAudit->slug
                        ]
                    ],
                    'store'  => [
                        'name'       => 'grp.models.fulfilment-customer.stored-items.store',
                        'parameters' => [
                            'fulfilmentCustomer' => $storedItemAudit->fulfilmentCustomer->id
                        ]
                    ],
                    'delete' => [
                        'name' => 'grp.models.stored-items.delete'
                    ]
                ],

                'pallet'                  => PalletResource::make($pallet),

                'data'                    => StoredItemAuditResource::make($storedItemAudit),
                'editDeltas'              => $editDeltas,
                'stored_item_deltas'      => $deltas,
                'fulfilment_customer'     => FulfilmentCustomerResource::make($storedItemAudit->fulfilmentCustomer)->getArray()
            ]
        );

        if ($storedItemAudit->state === StoredItemAuditStateEnum::IN_PROCESS) {
            $render->table(
                IndexStoredItemDeltasInProcessForPallet::make()->tableStructure(
                    $pallet,
                    prefix: 'edit_stored_item_deltas'
                )
            );
        } else {
            $render->table(
                IndexStoredItemAuditDeltas::make()->tableStructure(
                    $storedItemAudit,
                    prefix: 'stored_item_deltas',
                )
            );
        }

        return $render;
    }

    /** @noinspection PhpUnusedParameterInspection */
    public function inPalletInFulfilmentCustomer(Organisation $organisation, Fulfilment $fulfilment, FulfilmentCustomer $fulfilmentCustomer, Pallet $pallet, StoredItemAudit $storedItemAudit, ActionRequest $request): StoredItemAudit
    {
        $this->parent = $fulfilmentCustomer;
        $this->pallet = $pallet;
        $this->initialisationFromFulfilment($fulfilment, $request);

        return $this->handle($storedItemAudit);
    }

    public function inWarehouse(Organisation $organisation, Warehouse $warehouse, Pallet $pallet, StoredItemAudit $storedItemAudit, ActionRequest $request): StoredItemAudit
    {
        $this->parent = $warehouse;
        $this->pallet = $pallet;
        $this->initialisationFromWarehouse($warehouse, $request);

        return $this->handle($storedItemAudit);
    }

    public function getBreadcrumbs(StoredItemAudit $storedItemAudit, string $routeName, array $routeParameters, $suffix = ''): array
    {
        $headCrumb = function (array $routeParameters, string $suffix) use ($storedItemAudit) {
            return [
                [
                    'type'           => 'modelWithIndex',
                    'modelWithIndex' => [
                        'index' => [
                            'route' => $routeParameters['index'],
                            'label' => __('pallet skus audits')
                        ],
                        'model' => [
                            'route' => $routeParameters['model'],
                            'label' => $storedItemAudit->slug,
                        ],
                    ],
                    'suffix'         => $suffix
                ],
            ];
        };


        return match ($routeName) {
            'grp.org.fulfilments.show.crm.customers.show.pallets.stored-item-audits.show' =>
            array_merge(
                ShowPallet::make()->getBreadcrumbs($storedItemAudit->fulfilmentCustomer, $routeParameters),
                $headCrumb(
                    [
                        'index' => [
                            'name'       => 'grp.org.fulfilments.show.crm.customers.show.pallets.show',
                            'parameters' => $routeParameters
                        ],
                        'model' => [
                            'name'       => 'grp.org.fulfilments.show.crm.customers.show.pallets.stored-item-audits.show',
                            'parameters' => $routeParameters
                        ]
                    ],
                    $suffix
                )
            ),
            'grp.org.warehouses.show.inventory.pallets.show.stored-item-audit.show' =>
            array_merge(
                ShowPallet::make()->getBreadcrumbs($storedItemAudit->warehouse, $routeParameters),
                $headCrumb(
                    [
                        'index' => [
                            'name'       => 'grp.org.warehouses.show.inventory.pallets.current.show',
                            'parameters' => $routeParameters
                        ],
                        'model' => [
                            'name'       => 'grp.org.warehouses.show.inventory.pallets.show.stored-item-audit.show',
                            'parameters' => $routeParameters
                        ]
                    ],
                    $suffix
                )
            ),
            default => []
        };
    }
}
