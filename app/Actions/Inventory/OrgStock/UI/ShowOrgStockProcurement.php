<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 01 Jan 2026 20:50:16 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Inventory\OrgStock\UI;

use App\Actions\OrgAction;
use App\Actions\Procurement\PurchaseOrder\UI\IndexPurchaseOrders;
use App\Actions\Traits\Authorisations\Inventory\WithInventoryAuthorisation;
use App\Enums\UI\Procurement\OrgStockProcurementTabsEnum;
use App\Http\Resources\Procurement\PurchaseOrdersResource;
use App\Models\Inventory\OrgStock;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;

class ShowOrgStockProcurement extends OrgAction
{
    use WithInventoryAuthorisation;
    use WithOrgStock;
    use WithOrgStockNavigation;
    use WithOrgStockSubNavigation;

    private string $tabsEnum = OrgStockProcurementTabsEnum::class;

    public function handle(OrgStock $orgStock): OrgStock
    {
        return $orgStock;
    }

    public function htmlResponse(OrgStock $orgStock, ActionRequest $request): Response
    {
        $hasMaster     = $orgStock->stock;
        $subNavigation = $this->getOrgStockSubNavigation($orgStock, $request);

        return Inertia::render(
            'Org/Inventory/OrgStock',
            [
                'title'       => __('SKU').' '.$orgStock->code.' ('.__('Procurement').')',
                'breadcrumbs' => $this->getBreadcrumbs(
                    $orgStock,
                    $request->route()->getName(),
                    $request->route()->originalParameters()
                ),
                'navigation'  => [
                    'previous' => $this->getPreviousModel($orgStock, $request),
                    'next'     => $this->getNextModel($orgStock, $request),
                ],
                'pageHead'    => [
                    'icon'          => [
                        'title' => __('SKU').' ('.__('Procurement').')',
                        'icon'  => 'fal fa-box'
                    ],
                    'model'         => __('SKU'),
                    'title'         => $orgStock->code,
                    'subNavigation' => $subNavigation
                ],
                'tabs'        => [
                    'current'    => $this->tab,
                    'navigation' => $this->tabsEnum::navigation()
                ],

                'master'      => $hasMaster,
                'masterRoute' => $hasMaster ? [
                    'name'       => 'grp.goods.stocks.show',
                    'parameters' => [
                        'stock' => $orgStock->stock->slug
                    ]
                ] : null,

                $this->tabsEnum::PURCHASE_ORDERS->value => $this->tab == $this->tabsEnum::PURCHASE_ORDERS->value ?
                    fn() => PurchaseOrdersResource::collection(IndexPurchaseOrders::run($orgStock, $this->tabsEnum::PURCHASE_ORDERS->value))
                    : Inertia::lazy(fn() => PurchaseOrdersResource::collection(IndexPurchaseOrders::run($orgStock, $this->tabsEnum::PURCHASE_ORDERS->value))),

            ]
        )->table(IndexPurchaseOrders::make()->tableStructure($orgStock, prefix: $this->tabsEnum::PURCHASE_ORDERS->value));
    }

    public function getBreadcrumbs(OrgStock $orgStock, string $routeName, array $routeParameters): array
    {
        $routeName = preg_replace('/.procurement$/', '', $routeName);

        return ShowOrgStock::make()->getBreadcrumbs($orgStock, $routeName, $routeParameters, '('.__('Procurement').')');
    }

}
