<?php

/*
 * author Arya Permana - Kirin
 * created on 13-01-2025-16h-41m
 * github: https://github.com/KirinZero0
 * copyright 2025
*/

namespace App\Actions\Retina\Storage\StoredItemsAudit\UI;

use App\Actions\Fulfilment\StoredItemAudit\EditStoredItemDeltasInAudit;
use App\Actions\Fulfilment\Pallet\UI\IndexPalletsInAudit;
use App\Actions\RetinaAction;
use App\Actions\Traits\Authorisations\HasFulfilmentAssetsAuthorisation;
use App\Actions\UI\Retina\Billing\UI\ShowRetinaBillingDashboard;
use App\Actions\UI\Retina\Storage\UI\ShowRetinaStorageDashboard;
use App\Enums\Fulfilment\StoredItemAudit\StoredItemAuditStateEnum;
use App\Http\Resources\Fulfilment\FulfilmentCustomerResource;
use App\Http\Resources\Fulfilment\PalletsResource;
use App\Http\Resources\Fulfilment\StoredItemAuditResource;
use App\Models\Fulfilment\StoredItemAudit;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;

class ShowRetinaStoredItemAudit extends RetinaAction
{
    use HasFulfilmentAssetsAuthorisation;

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

        $actions = [];
        if ($storedItemAudit->state === StoredItemAuditStateEnum::IN_PROCESS) {
            $actions = [
                [
                    'type'  => 'button',
                    'style' => 'primary',
                    'label' => __('Complete Audit'),
                    'route' => [
                        'method' => 'patch',
                        'name'       => 'grp.models.fulfilment-customer.stored_item_audits.complete',
                        'parameters' => [
                            'fulfilmentCustomer' => $storedItemAudit->fulfilment_customer_id,
                            'storedItemAudit' => $storedItemAudit->id
                        ],
                    ]
                ]
            ];
        }

        return Inertia::render(
            'Storage/RetinaStoredItemsAudit',
            [
                'breadcrumbs' => $this->getBreadcrumbs(
                    $request->route()->getName(),
                    $request->route()->originalParameters()
                ),
                'title'       => __("Customer's skus audits"),
                'pageHead'    => [
                    'title'      => $title,
                    'afterTitle' => $afterTitle,
                    'iconRight'  => $iconRight,
                    'icon'       => $icon,
                    'actions' => $actions,
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
                        'name'       => '',
                        'parameters' => []
                    ]
                ],

                'storedItemsRoute' => [
                    'index'  => [
                        'name'       => '',
                        'parameters' => []
                    ],
                    'store'  => [
                        'name'       => '',
                        'parameters' => []
                    ],
                    'delete' => [
                        'name' => ''
                    ]
                ],



                'data'                => StoredItemAuditResource::make($storedItemAudit),
                'pallets'             => PalletsResource::collection(EditStoredItemDeltasInAudit::run($storedItemAudit->fulfilmentCustomer, 'pallets')),
                'fulfilment_customer' => FulfilmentCustomerResource::make($storedItemAudit->fulfilmentCustomer)->getArray()
            ]
        )->table(
            EditStoredItemDeltasInAudit::make()->tableStructure(
                $storedItemAudit->fulfilmentCustomer,
                prefix: 'pallets'
            )
        );
    }

    public function asController(StoredItemAudit $storedItemAudit, ActionRequest $request): StoredItemAudit
    {
        $this->initialisation($request);

        return $this->handle($storedItemAudit);
    }

    public function getBreadcrumbs(string $routeName, array $routeParameters, $suffix = ''): array
    {
        $headCrumb = function (StoredItemAudit $storedItemAudit, array $routeParameters, string $suffix) {
            return [
                [
                    'type'           => 'modelWithIndex',
                    'modelWithIndex' => [
                        'index' => [
                            'route' => $routeParameters['index'],
                            'label' => __('SKUs Audit')
                        ],
                        'model' => [
                            'route' => $routeParameters['model'],
                            'label' => $storedItemAudit->slug,
                        ],

                    ],
                    'suffix' => $suffix
                ],
            ];
        };

        $storedItemAudit = StoredItemAudit::where('slug', $routeParameters['storedItemAudit'])->first();

        return match ($routeName) {
            'retina.storage.stored-items-audits.show' => array_merge(
                ShowRetinaStorageDashboard::make()->getBreadcrumbs(),
                $headCrumb(
                    $storedItemAudit,
                    [
                        'index' => [
                            'name'       => 'retina.storage.stored-items-audits.index',
                            'parameters' => []
                        ],
                        'model' => [
                            'name'       => 'retina.storage.stored-items-audits.show',
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
