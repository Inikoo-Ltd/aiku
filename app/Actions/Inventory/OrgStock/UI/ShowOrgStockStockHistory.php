<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 01 Jan 2026 20:50:16 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Inventory\OrgStock\UI;

use App\Actions\Inventory\OrgStockMovement\UI\IndexOrgStockMovements;
use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\Inventory\WithInventoryAuthorisation;
use App\Enums\UI\Procurement\OrgStockStockHistoryTabsEnum;
use App\Http\Resources\Inventory\OrgStockMovementsResource;
use App\Models\Inventory\OrgStock;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;

class ShowOrgStockStockHistory extends OrgAction
{
    use WithInventoryAuthorisation;
    use WithOrgStock;
    use WithOrgStockNavigation;
    use WithOrgStockSubNavigation;

    private string $tabsEnum = OrgStockStockHistoryTabsEnum::class;

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
                'title'       => __('SKU').' '.$orgStock->code.' ('.__('Stock History').')',
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
                        'title' => __('SKU').' ('.__('Stock History').')',
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

                OrgStockStockHistoryTabsEnum::STOCK_HISTORY->value => $this->tab == OrgStockStockHistoryTabsEnum::STOCK_HISTORY->value ?
                    fn() => OrgStockMovementsResource::collection(IndexOrgStockMovements::run($orgStock, OrgStockStockHistoryTabsEnum::STOCK_HISTORY->value))
                    : Inertia::lazy(fn() => OrgStockMovementsResource::collection(IndexOrgStockMovements::run($orgStock, OrgStockStockHistoryTabsEnum::STOCK_HISTORY->value))),

            ]
        )
            ->table(IndexOrgStockMovements::make()->tableStructure($orgStock, prefix: OrgStockStockHistoryTabsEnum::STOCK_HISTORY->value));
    }

    public function getBreadcrumbs(OrgStock $orgStock, string $routeName, array $routeParameters): array
    {
        $routeName = preg_replace('/.stock_history$/', '', $routeName);

        return ShowOrgStock::make()->getBreadcrumbs($orgStock, $routeName, $routeParameters, '('.__('Stock History').')');
    }

}
