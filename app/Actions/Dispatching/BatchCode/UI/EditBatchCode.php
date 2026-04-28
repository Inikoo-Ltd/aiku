<?php

/*
 * Author: stewicca <stewicalf@gmail.com>
 * Created: Mon, 21 Apr 2026, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Steven Wicca Alfredo
 */

namespace App\Actions\Dispatching\BatchCode\UI;

use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\Inventory\WithInventoryAuthorisation;
use App\Models\Dispatching\BatchCode;
use App\Models\Inventory\Warehouse;
use App\Models\SysAdmin\Organisation;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;

class EditBatchCode extends OrgAction
{
    use WithInventoryAuthorisation;

    public function asController(Organisation $organisation, Warehouse $warehouse, BatchCode $batchCode, ActionRequest $request): Response
    {
        $this->initialisationFromWarehouse($warehouse, $request);

        return $this->handle($batchCode, $request);
    }

    public function handle(BatchCode $batchCode, ActionRequest $request): Response
    {
        return Inertia::render(
            'EditModel',
            [
                'breadcrumbs' => $this->getBreadcrumbs($batchCode, $request->route()->originalParameters()),
                'title'       => __('Edit Batch Code').' '.$batchCode->code,
                'pageHead'    => [
                    'title'   => __('Edit Batch Code'),
                    'actions' => [
                        [
                            'type'  => 'button',
                            'style' => 'cancel',
                            'label' => __('Exit edit'),
                            'route' => [
                                'name'       => 'grp.org.warehouses.show.inventory.batch_codes.show',
                                'parameters' => $request->route()->originalParameters(),
                            ],
                        ],
                    ],
                ],
                'formData' => [
                    'blueprint' => [
                        [
                            'label'  => __('Batch Code'),
                            'icon'   => 'fa-light fa-barcode',
                            'title'  => __('Batch Code'),
                            'fields' => [
                                'org_stock_id' => [
                                    'type'        => 'select_infinite',
                                    'label'       => __('SKU'),
                                    'placeholder' => __('Select SKU'),
                                    'mode'        => 'single',
                                    'searchable'  => true,
                                    'labelProp'   => 'name',
                                    'valueProp'   => 'id',
                                    'fetchRoute'  => [
                                        'name'       => 'grp.json.org_stocks.index',
                                        'parameters' => [
                                            'organisation' => $batchCode->organisation_id,
                                        ],
                                    ],
                                    'value' => $batchCode->org_stock_id,
                                ],
                                'code' => [
                                    'type'  => 'input',
                                    'label' => __('Code'),
                                    'value' => $batchCode->code,
                                ],
                                'expiry_date' => [
                                    'type'  => 'date',
                                    'label' => __('Expiry Date'),
                                    'value' => $batchCode->expiry_date?->format('Y-m-d'),
                                ],
                            ],
                        ],
                    ],
                    'args' => [
                        'updateRoute' => [
                            'name'       => 'grp.models.batch_code.update',
                            'parameters' => ['batchCode' => $batchCode->id],
                        ],
                    ],
                ],
            ]
        );
    }

    public function getBreadcrumbs(BatchCode $batchCode, array $routeParameters): array
    {
        return array_merge(
            ShowBatchCode::make()->getBreadcrumbs($batchCode, $routeParameters, '('.__('Editing').')'),
            []
        );
    }
}
