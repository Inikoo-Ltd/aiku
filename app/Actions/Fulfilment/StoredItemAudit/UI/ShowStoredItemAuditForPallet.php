<?php

/*
 * author Arya Permana - Kirin
 * created on 21-02-2025-08h-27m
 * github: https://github.com/KirinZero0
 * copyright 2025
*/

namespace App\Actions\Fulfilment\StoredItemAudit\UI;

use App\Actions\Fulfilment\FulfilmentCustomer\ShowFulfilmentCustomer;
use App\Actions\Fulfilment\Pallet\UI\ShowPallet;
use App\Actions\Fulfilment\StoredItemAuditDelta\UI\IndexStoredItemAuditDeltas;
use App\Actions\Fulfilment\WithFulfilmentCustomerSubNavigation;
use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\WithFulfilmentAuthorisation;
use App\Http\Resources\Fulfilment\StoredItemAuditResource;
use App\Http\Resources\Fulfilment\StoredItemDeltasInProcessForPalletResource;
use App\Http\Resources\Fulfilment\StoredItemDeltasInProcessResource;
use App\Models\Fulfilment\Fulfilment;
use App\Models\Fulfilment\FulfilmentCustomer;
use App\Models\Fulfilment\Pallet;
use App\Models\Fulfilment\StoredItemAudit;
use App\Models\Inventory\Location;
use App\Models\SysAdmin\Organisation;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;

class ShowStoredItemAuditForPallet extends OrgAction
{
    use WithFulfilmentAuthorisation;
    use WithFulfilmentCustomerSubNavigation;

    private Fulfilment|Location|FulfilmentCustomer $parent;
    private Pallet $pallet;

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
        $disabled = $storedItemAudit->deltas->every(function (StoredItemAuditDelta $delta) {
            return $delta->storedItem === null;
        });
        $actions = [];
        $subNavigation = [];
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
            $editDeltas = StoredItemDeltasInProcessForPalletResource::collection(IndexStoredItemDeltasInProcessForPallet::run($storedItemAudit, 'edit_stored_item_deltas'));
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
                'pageHead' => [
                    'title'     => __('Audit'),
                    'model'     => __('Pallet'),
                    // 'afterTitle' => $afterTitle,
                    'icon'      => [
                        'icon'  => ['fal', 'fa-pallet'],
                        'title' => __('Pallet'),
                    ],
                    'subNavigation' => $subNavigation,
                    'actions'       => $actions,
                    // 'edit' => $this->canEdit ? [
                    //     'route' => [
                    //         'name'       => preg_replace('/show$/', 'edit', $request->route()->getName()),
                    //         'parameters' => array_values($request->route()->originalParameters())
                    //     ]
                    // ] : false,
                    // 'actions' => $actions
                ],
                'edit_stored_item_deltas' => null,
                'stored_item_deltas' => null,

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
                IndexStoredItemDeltasInProcessForPallet::make()->tableStructure(
                    $storedItemAudit->scope,
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
                ShowPallet::make()->getBreadcrumbs($storedItemAudit->fulfilmentCustomer, $routeName, $routeParameters),
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
            default => []
        };
    }
}
