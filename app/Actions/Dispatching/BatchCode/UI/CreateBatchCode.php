<?php

/*
 * Author: stewicca <stewicalf@gmail.com>
 * Created: Mon, 21 Apr 2026, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Steven Wicca Alfredo
 */

namespace App\Actions\Dispatching\BatchCode\UI;

use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\Inventory\WithInventoryAuthorisation;
use App\Models\Inventory\OrgStock;
use App\Models\Inventory\Warehouse;
use App\Models\SysAdmin\Organisation;
use Illuminate\Contracts\Routing\UrlRoutable;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;

class CreateBatchCode extends OrgAction
{
    use WithInventoryAuthorisation;

    public function asController(Organisation $organisation, Warehouse $warehouse, ActionRequest $request): Response
    {
        $this->initialisationFromWarehouse($warehouse, $request);

        return $this->handle($request, null);
    }

    public function inOrgStock(Organisation $organisation, Warehouse $warehouse, OrgStock $orgStock, ActionRequest $request): Response
    {
        $this->initialisationFromWarehouse($warehouse, $request);

        return $this->handle($request, $orgStock);
    }

    public function handle(ActionRequest $request, ?OrgStock $orgStock): Response
    {
        $redirectRoutePayload = $this->getRedirectRoutePayload($request);
        $redirectRouteName    = $redirectRoutePayload['redirect_route_name'];
        $fields = [
            'code'        => [
                'type'     => 'input',
                'label'    => __('Batch code'),
                'required' => true,
            ],
            'expiry_date' => [
                'type'  => 'date',
                'label' => __('Expiry Date'),
            ],
        ];

        if (!$orgStock) {
            $fields = [
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
                            'organisation' => $this->warehouse->organisation_id,
                        ],
                    ],
                    'required'    => true,
                    'value'       => null,
                ],
                ...$fields,
            ];
        }

        return Inertia::render(
            'CreateModel',
            [
                'breadcrumbs' => $orgStock
                    ? $this->getOrgStockBreadcrumbs($orgStock, $request)
                    : $this->getBreadcrumbs($request->route()->originalParameters()),
                'title'       => __('Batch Code') . ': ' . __('create new'),
                'pageHead'    => [
                    'title'   => __('Create new'),
                    'icon'  => 'fal fa-barcode',
                    'model' => __('Batch code'),
                    'actions' => [
                        [
                            'type'  => 'button',
                            'style' => 'cancel',
                            'label' => __('Cancel'),
                            'route' => [
                                'name'       => $redirectRouteName,
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
                            'fields' => $fields,
                        ],
                    ],
                    'route'     => [
                        'name'       => 'grp.models.warehouse.batch_code.store',
                        'parameters' => ['warehouse' => $this->warehouse->id],
                        'body'       => [
                            ...$redirectRoutePayload,
                            ...($orgStock ? ['org_stock_id' => $orgStock->id] : []),
                        ],
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

    public function getOrgStockBreadcrumbs(OrgStock $orgStock, ActionRequest $request): array
    {
        return array_merge(
            IndexBatchCodes::make()->getOrgStockBreadcrumbs($orgStock, $request),
            [
                [
                    'type'          => 'creatingModel',
                    'creatingModel' => ['label' => __('Creating batch code')],
                ],
            ]
        );
    }

    public function getRedirectRoutePayload(ActionRequest $request): array
    {
        $routeName = preg_replace('/\.create$/', '', $request->route()->getName());

        if (!Route::has($routeName)) {
            $routeName .= '.index';
        }

        return [
            'redirect_route_name'       => $routeName,
            'redirect_route_parameters' => collect($request->route()->originalParameters())
                ->map(fn ($value) => $value instanceof UrlRoutable ? $value->getRouteKey() : $value)
                ->toArray(),
        ];
    }
}
