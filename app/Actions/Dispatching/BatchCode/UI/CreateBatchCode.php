<?php

/*
 * Author: stewicca <stewicalf@gmail.com>
 * Created: Mon, 21 Apr 2026, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Steven Wicca Alfredo
 */

namespace App\Actions\Dispatching\BatchCode\UI;

use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\Inventory\WithInventoryAuthorisation;
use App\Models\Inventory\Warehouse;
use App\Models\SysAdmin\Organisation;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;

class CreateBatchCode extends OrgAction
{
    use WithInventoryAuthorisation;

    public function asController(Organisation $organisation, Warehouse $warehouse, ActionRequest $request): Response
    {
        $this->initialisationFromWarehouse($warehouse, $request);

        return $this->handle($request);
    }

    public function handle(ActionRequest $request): Response
    {
        return Inertia::render(
            'CreateModel',
            [
                'breadcrumbs' => $this->getBreadcrumbs($request->route()->originalParameters()),
                'title'       => __('New Batch Code'),
                'pageHead'    => [
                    'title'   => __('New Batch Code'),
                    'actions' => [
                        [
                            'type'  => 'button',
                            'style' => 'cancel',
                            'label' => __('Cancel'),
                            'route' => [
                                'name'       => 'grp.org.warehouses.show.inventory.batch_codes.index',
                                'parameters' => $request->route()->originalParameters(),
                            ],
                        ],
                    ],
                ],
                'formData'    => [
                    'blueprint' => [
                        [
                            'label'  => __('Batch Code'),
                            'icon'   => 'fa-light fa-barcode',
                            'title'  => __('Batch Code'),
                            'fields' => [
                                'code'         => [
                                    'type'     => 'input',
                                    'label'    => __('Code'),
                                    'required' => true,
                                ],
                                'expiry_date'  => [
                                    'type'  => 'date',
                                    'label' => __('Expiry Date'),
                                ],
                                'org_stock_id' => [
                                    'type'        => 'select_infinite',
                                    'label'       => __('SKU'),
                                    'placeholder' => __('Select SKU'),
                                    'mode'        => 'single',
                                    'searchable'  => true,
                                    'labelProp'   => 'code',
                                    'valueProp'   => 'id',
                                    'fetchRoute'  => [
                                        'name'       => 'grp.json.org_stocks.index',
                                        'parameters' => [
                                            'organisation' => $this->warehouse->organisation_id,
                                        ],
                                    ],
                                    'required'    => true,
                                    'value'       => null,
                                ],
                            ],
                        ],
                    ],
                    'route'     => [
                        'name'       => 'grp.models.warehouse.batch_code.store',
                        'parameters' => ['warehouse' => $this->warehouse->id],
                    ],
                ],
            ]
        );
    }

    public function getBreadcrumbs(array $routeParameters): array
    {
        return array_merge(
            IndexBatchCodes::make()->getBreadcrumbs($routeParameters, __('creating')),
            [
                [
                    'type'          => 'creatingModel',
                    'creatingModel' => ['label' => __('Creating batch code')],
                ],
            ]
        );
    }
}
