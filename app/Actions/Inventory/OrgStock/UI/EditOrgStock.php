<?php
/*
 * author Louis Perez
 * created on 20-11-2025-16h-35m
 * github: https://github.com/louis-perez
 * copyright 2025
*/

namespace App\Actions\Inventory\OrgStock\UI;

use App\Actions\Catalogue\Product\UI\IndexProductsInOrgStock;
use App\Actions\Goods\StockFamily\UI\ShowStockFamily;
use App\Actions\Helpers\History\UI\IndexHistory;
use App\Actions\Inventory\UI\ShowInventoryDashboard;
use App\Actions\OrgAction;
use App\Actions\Procurement\PurchaseOrder\UI\IndexPurchaseOrders;
use App\Actions\Traits\Authorisations\Inventory\WithInventoryAuthorisation;
use App\Enums\UI\Procurement\OrgStockTabsEnum;
use App\Http\Resources\Catalogue\ProductsResource;
use App\Http\Resources\History\HistoryResource;
use App\Http\Resources\Inventory\OrgStockResource;
use App\Http\Resources\Procurement\PurchaseOrdersResource;
use App\Models\Goods\StockFamily;
use App\Models\Inventory\OrgStock;
use App\Models\Inventory\Warehouse;
use App\Models\SysAdmin\Organisation;
use Illuminate\Support\Arr;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;

class EditOrgStock extends OrgAction
{
    use WithInventoryAuthorisation;


    private Organisation|StockFamily $parent;

    public function handle(OrgStock $orgStock): OrgStock
    {
        return $orgStock;
    }


    public function asController(Organisation $organisation, Warehouse $warehouse, OrgStock $orgStock, ActionRequest $request): OrgStock
    {
        $this->parent = $organisation;
        $this->initialisationFromWarehouse($warehouse, $request)->withTab(OrgStockTabsEnum::values());

        return $this->handle($orgStock);
    }

    public function maya(Organisation $organisation, Warehouse $warehouse, OrgStock $orgStock, ActionRequest $request): OrgStock
    {
        $this->maya   = true;
        $this->parent = $organisation;
        $this->initialisationFromWarehouse($warehouse, $request)->withTab(OrgStockTabsEnum::values());

        return $this->handle($orgStock);
    }


    public function htmlResponse(OrgStock $orgStock, ActionRequest $request): Response
    {
        $warning = null;
        $warning = [
            'type'  => 'warning',
            'title' => __('Important'),
            'text'  => __('Products relies on SKU data. Editing it would affect the related product display behavior'),
            'icon'  => ['fas', 'fa-exclamation-triangle']
        ];

        return Inertia::render(
            'EditModel',
            [
                'title'       => __('Editing SKU').' '.$orgStock->code,
                'warning'     => $warning,
                'breadcrumbs' => $this->getBreadcrumbs(
                    $orgStock,
                    $request->route()->getName(),
                    $request->route()->originalParameters(),
                    '(editing)'
                ),
                'navigation' => [],
                'pageHead'                        => [
                    'icon'  => [
                        'title' => __('Sku'),
                        'icon'  => 'fal fa-box'
                    ],
                    'model' => __('SKU'),
                    'title' => $orgStock->code,
                ],
                'formData' => [
                    'blueprint' => $this->getBlueprint($orgStock),
                    'args'      => [
                        'updateRoute' => [
                            'name'       => 'grp.org.warehouses.show.inventory.org_stocks.update',
                            'parameters' => $request->route()->originalParameters(),
                        ],
                    ]
                ]

            ]
        );
    }

    /**
     * @throws \Exception
     */
    public function getBlueprint(OrgStock $orgStock): array
    {
        return [
            [
                'label'  => __('Stock Data'),
                'icon'   => 'fa-light fa-fingerprint',
                'fields' => [
                    'is_on_demand'        => [
                        'type'  => 'toggle',
                        'label' => __('Is On Demand'),
                        'value' => $orgStock->is_on_demand
                    ],
                ]
            ]
        ];
    }

    public function jsonResponse(OrgStock $orgStock): OrgStockResource
    {
        return new OrgStockResource($orgStock);
    }

    public function getBreadcrumbs(OrgStock $orgStock, string $routeName, array $routeParameters, $suffix = null): array
    {
        $headCrumb = function (OrgStock $orgStock, array $routeParameters, $suffix) {
            return [
                [
                    'type'           => 'modelWithIndex',
                    'modelWithIndex' => [
                        'index' => [
                            'route' => $routeParameters['index'],
                            'label' => __('SKUs')
                        ],
                        'model' => [
                            'route' => $routeParameters['model'],
                            'label' => $orgStock->code,
                        ],
                    ],
                    'suffix'         => $suffix,

                ],
            ];
        };

        return match ($routeName) { 
                'grp.org.warehouses.show.inventory.org_stocks.all_org_stocks.edit',
                'grp.org.warehouses.show.inventory.org_stocks.current_org_stocks.edit',
                'grp.org.warehouses.show.inventory.org_stocks.active_org_stocks.edit',
                'grp.org.warehouses.show.inventory.org_stocks.in_process_org_stocks.edit',
                'grp.org.warehouses.show.inventory.org_stocks.discontinuing_org_stocks.edit',
                'grp.org.warehouses.show.inventory.org_stocks.discontinued_org_stocks.edit',
                'grp.org.warehouses.show.inventory.org_stocks.abnormality_org_stocks.edit',
                'maya.org.warehouses.show.inventory.org_stocks.edit' =>
                    array_merge(
                        (new ShowInventoryDashboard())->getBreadcrumbs($routeParameters),
                        $headCrumb(
                            $orgStock,
                            [
                                'index' => [
                                    'name'       => preg_replace('/\.edit$/', '.index', $routeName),
                                    'parameters' => $routeParameters
                                ],
                                'model' => [
                                    'name'       => preg_replace('/\.edit$/', '.show', $routeName),
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
