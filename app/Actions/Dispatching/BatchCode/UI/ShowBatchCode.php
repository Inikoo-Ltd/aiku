<?php

/*
 * Author: stewicca <stewicalf@gmail.com>
 * Created: Mon, 21 Apr 2026, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Steven Wicca Alfredo
 */

namespace App\Actions\Dispatching\BatchCode\UI;

use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\Inventory\WithInventoryAuthorisation;
use App\Enums\UI\Inventory\BatchCodeTabsEnum;
use App\Http\Resources\Dispatching\BatchCodeResource;
use App\Http\Resources\Dispatching\DeliveryNotesResource;
use App\Models\Dispatching\BatchCode;
use App\Models\Inventory\Warehouse;
use App\Models\SysAdmin\Organisation;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;

class ShowBatchCode extends OrgAction
{
    use WithInventoryAuthorisation;

    public function asController(Organisation $organisation, Warehouse $warehouse, BatchCode $batchCode, ActionRequest $request): BatchCode
    {
        $this->initialisationFromWarehouse($warehouse, $request)->withTab(BatchCodeTabsEnum::values());

        return $batchCode;
    }

    public function htmlResponse(BatchCode $batchCode, ActionRequest $request): Response
    {
        return Inertia::render(
            'Org/Inventory/BatchCode',
            [
                'breadcrumbs' => $this->getBreadcrumbs($batchCode, $request->route()->originalParameters()),
                'title'       => $batchCode->code,
                'pageHead'    => [
                    'icon'    => ['icon' => ['fal', 'fa-barcode'], 'title' => __('Batch Code')],
                    'model'   => __('Batch Code'),
                    'title'   => $batchCode->code,
                    'actions' => [
                        $this->canEdit ? [
                            'type'  => 'button',
                            'style' => 'edit',
                            'route' => [
                                'name'       => 'grp.org.warehouses.show.inventory.batch_codes.edit',
                                'parameters' => $request->route()->originalParameters(),
                            ],
                        ] : false,
                        $this->canEdit ? [
                            'type'   => 'button',
                            'style'  => 'delete',
                            'key'    => 'delete_batch_code',
                            'route'  => [
                                'name'       => 'grp.models.batch_code.delete',
                                'parameters' => ['batchCode' => $batchCode->id],
                                'method'     => 'delete',
                            ],
                        ] : false,
                    ],
                    'exports' => [
                        [
                            'routes' => [
                                [
                                    'label'   => __('Excel'),
                                    'key'     => 'xlsx',
                                    'icon'    => ['fal', 'fa-file-excel'],
                                    'popover' => false,
                                    'route'   => [
                                        'name'       => 'grp.org.warehouses.show.inventory.batch_codes.delivery_notes.export',
                                        'parameters' => array_merge($request->route()->originalParameters(), ['type' => 'xlsx']),
                                    ],
                                ],
                                [
                                    'label'          => __('CSV'),
                                    'key'            => 'csv',
                                    'icon'           => ['fal', 'fa-file-csv'],
                                    'inside_popover' => true,
                                    'route'          => [
                                        'name'       => 'grp.org.warehouses.show.inventory.batch_codes.delivery_notes.export',
                                        'parameters' => array_merge($request->route()->originalParameters(), ['type' => 'csv']),
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
                'tabs' => [
                    'current'    => $this->tab,
                    'navigation' => BatchCodeTabsEnum::navigation(),
                ],
                'download_route' => [
                    'name'       => 'grp.org.warehouses.show.inventory.batch_codes.delivery_notes.export',
                    'parameters' => $request->route()->originalParameters(),
                ],
                'batch_code' => BatchCodeResource::make($batchCode->load('orgStock'))->resolve(),

                BatchCodeTabsEnum::DELIVERY_NOTES->value => $this->tab == BatchCodeTabsEnum::DELIVERY_NOTES->value
                    ? fn () => DeliveryNotesResource::collection(IndexDeliveryNotesInBatchCode::run($batchCode, BatchCodeTabsEnum::DELIVERY_NOTES->value))
                    : Inertia::lazy(fn () => DeliveryNotesResource::collection(IndexDeliveryNotesInBatchCode::run($batchCode, BatchCodeTabsEnum::DELIVERY_NOTES->value))),
            ]
        )->table(IndexDeliveryNotesInBatchCode::make()->tableStructure(
            prefix: BatchCodeTabsEnum::DELIVERY_NOTES->value,
            exportLinks: [
                'export' => [
                    'route' => [
                        'name'       => 'grp.org.warehouses.show.inventory.batch_codes.delivery_notes.export',
                        'parameters' => $request->route()->originalParameters(),
                    ],
                ],
            ]
        ));
    }

    public function getBreadcrumbs(BatchCode $batchCode, array $routeParameters, ?string $suffix = null): array
    {
        return array_merge(
            IndexBatchCodes::make()->getBreadcrumbs(
                array_diff_key($routeParameters, ['batchCode' => null])
            ),
            [
                [
                    'type'   => 'simple',
                    'simple' => [
                        'route'  => [
                            'name'       => 'grp.org.warehouses.show.inventory.batch_codes.show',
                            'parameters' => $routeParameters,
                        ],
                        'label'  => $batchCode->code,
                    ],
                    'suffix' => $suffix,
                ],
            ]
        );
    }
}
