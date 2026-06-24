<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 26 Jan 2024 18:40:36 Malaysia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Fulfilment\StoredItemAudit\UI;

use App\Actions\Fulfilment\FulfilmentCustomer\ShowFulfilmentCustomer;
use App\Actions\Fulfilment\StoredItemAuditDelta\UI\IndexStoredItemAuditDeltas;
use App\Actions\Fulfilment\WithFulfilmentCustomerSubNavigation;
use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\WithFulfilmentShopAuthorisation;
use App\Enums\Fulfilment\StoredItemAudit\StoredItemAuditStateEnum;
use App\Http\Resources\Fulfilment\FulfilmentCustomerResource;
use App\Http\Resources\Fulfilment\StoredItemAuditDeltasResource;
use App\Http\Resources\Fulfilment\StoredItemAuditResource;
use App\Http\Resources\Fulfilment\StoredItemDeltasInProcessResource;
use App\Models\Fulfilment\Fulfilment;
use App\Models\Fulfilment\FulfilmentCustomer;
use App\Models\Fulfilment\StoredItemAudit;
use App\Models\Fulfilment\StoredItemAuditDelta;
use App\Models\Inventory\Location;
use App\Models\Inventory\Warehouse;
use App\Models\SysAdmin\Organisation;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;

class ShowStoredItemAudit extends OrgAction
{
    use WithFulfilmentShopAuthorisation;
    use WithFulfilmentCustomerSubNavigation;

    private Fulfilment|Location|FulfilmentCustomer $parent;

    private bool $selectStoredPallets = false;

    public function handle(StoredItemAudit $storedItemAudit): StoredItemAudit
    {
        return $storedItemAudit;
    }

    public function jsonResponse(StoredItemAudit $storedItemAudit): StoredItemAuditResource
    {
        return StoredItemAuditResource::make($storedItemAudit);
    }

    public function htmlResponse(StoredItemAudit $storedItemAudit, ActionRequest $request): Response
    {
        $subNavigation = [];

        $title      = __("Customer's SKUs audit");
        $icon       = ['fal', 'fa-pallet'];
        $afterTitle = null;
        $iconRight  = null;

        $disabled = $storedItemAudit->deltas->every(function (StoredItemAuditDelta $delta) {
            return $delta->storedItem === null;
        });

        $actions = [];
        $editDeltas = null;
        $deltas = null;
        if ($storedItemAudit->state === StoredItemAuditStateEnum::IN_PROCESS) {
            $actions = [
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
            $editDeltas = StoredItemDeltasInProcessResource::collection(IndexStoredItemDeltasInProcess::run($storedItemAudit, 'edit_stored_item_deltas'));
        } else {
            // todo
            $deltas = StoredItemAuditDeltasResource::collection(IndexStoredItemAuditDeltas::run($storedItemAudit, 'stored_item_deltas'));
        }


        if ($this->parent instanceof FulfilmentCustomer) {
            $subNavigation = $this->getFulfilmentCustomerSubNavigation($this->parent, $request);
        }


        $render = Inertia::render(
            'Org/Fulfilment/StoredItemAudit',
            [
                'breadcrumbs' => $this->getBreadcrumbs(
                    $storedItemAudit,
                    $request->route()->getName(),
                    $request->route()->originalParameters()
                ),
                'title'       => __("Customer's SKUs audits"),
                'pageHead'    => [
                    'title'         => $title,
                    'afterTitle'    => $afterTitle,
                    'iconRight'     => $iconRight,
                    'icon'          => $icon,
                    'actions'       => $actions,
                    'subNavigation' => $subNavigation,
                ],

                'notes_data' => [
                    [
                        'label'    => __('Public'),
                        'note'     => $storedItemAudit->public_notes ?? '',
                        'editable' => true,
                        'bgColor'  => 'pink',
                        'field'    => 'public_notes'
                    ],
                    [
                        'label'    => __('Private'),
                        'note'     => $storedItemAudit->internal_notes ?? '',
                        'editable' => true,
                        'bgColor'  => 'purple',
                        'field'    => 'internal_notes'
                    ],
                ],

                'route_list' => [
                    'update' => [
                        'name'       => 'grp.models.stored_item_audit.update',
                        'parameters' => [
                            'storedItemAudit' => $storedItemAudit->id
                        ]
                    ],
                    'stored_item_audit_delta' => [
                        'update' => [  // Update quantity
                            'method'     => 'patch',
                            'name'       => 'grp.models.stored_item_audit_delta.update',
                            //parameters: add the storedItemAuditDelta id in the FE
                        ],
                        'delete' => [
                            'method'     => 'delete',
                            'name'       => 'grp.models.stored_item_audit_delta.delete',
                            //parameters: add the storedItemAuditDelta id in the FE
                        ],
                        'store' => [
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
                        'name'       => 'grp.org.fulfilments.show.crm.customers.show.stored-items.index',
                        'parameters' => [
                            'organisation'       => $storedItemAudit->organisation->slug,
                            'fulfilment'         => $storedItemAudit->fulfilment->slug,
                            'fulfilmentCustomer' => $storedItemAudit->fulfilmentCustomer->slug,
                            'palletDelivery'     => $storedItemAudit->reference,
                            'filter'             => ['storable' => 'true'],
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

                'data'                      => StoredItemAuditResource::make($storedItemAudit),
                'edit_stored_item_deltas'   => $editDeltas,
                'stored_item_deltas'        => $deltas,
                'fulfilment_customer'       => FulfilmentCustomerResource::make($storedItemAudit->fulfilmentCustomer)->getArray()
            ]
        );


        if ($storedItemAudit->state === StoredItemAuditStateEnum::IN_PROCESS) {
            $render->table(
                IndexStoredItemDeltasInProcess::make()->tableStructure(
                    $storedItemAudit->fulfilmentCustomer,
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

    public function asController(Organisation $organisation, Warehouse $warehouse, Fulfilment $fulfilment, StoredItemAudit $storedItemAudit, ActionRequest $request): StoredItemAudit
    {
        $this->parent = $fulfilment;
        $this->initialisationFromFulfilment($fulfilment, $request);

        return $this->handle($storedItemAudit);
    }


    /** @noinspection PhpUnusedParameterInspection */
    public function inFulfilmentCustomer(Organisation $organisation, Fulfilment $fulfilment, FulfilmentCustomer $fulfilmentCustomer, StoredItemAudit $storedItemAudit, ActionRequest $request): StoredItemAudit
    {
        $this->parent = $fulfilmentCustomer;
        $this->initialisationFromFulfilment($fulfilment, $request);

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
                            'label' => __('Customer skus audits')
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
            'grp.org.fulfilments.show.crm.customers.show.stored-item-audits.show' =>
            array_merge(
                ShowFulfilmentCustomer::make()->getBreadcrumbs($routeParameters),
                $headCrumb(
                    [
                        'index' => [
                            'name'       => 'grp.org.fulfilments.show.crm.customers.show.stored-items.index',
                            'parameters' => $routeParameters
                        ],
                        'model' => [
                            'name'       => 'grp.org.fulfilments.show.crm.customers.show.stored-item-audits.show',
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
