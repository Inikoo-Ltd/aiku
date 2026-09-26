<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 26 Sep 2026 05:10:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Catalogue\Shop\UI;

use App\Actions\Catalogue\SalesAnalysis\GetShopSalesAnalysis;
use App\Actions\OrgAction;
use App\Models\Catalogue\Shop;
use App\Models\SysAdmin\Organisation;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;

class ShowShopSalesAnalysis extends OrgAction
{
    public function asController(Organisation $organisation, Shop $shop, ActionRequest $request): Response
    {
        $this->initialisationFromShop($shop, $request);

        return Inertia::render('Org/Catalogue/ShopSalesAnalysis', [
            'title'          => __('Sales analysis').' '.$shop->code,
            'breadcrumbs'    => array_merge(
                (new ShowShop())->getBreadcrumbs($request->route()->originalParameters()),
                [
                    [
                        'type'   => 'simple',
                        'simple' => [
                            'route' => [
                                'name'       => 'grp.org.shops.show.dashboard.sales_analysis',
                                'parameters' => $request->route()->originalParameters(),
                            ],
                            'label' => __('Sales analysis'),
                            'icon'  => 'fal fa-chart-line',
                        ],
                    ],
                ]
            ),
            'pageHead'       => [
                'title' => __('Sales analysis'),
                'model' => $shop->name,
                'icon'  => ['icon' => ['fal', 'fa-chart-line'], 'title' => __('Sales analysis')],
            ],
            'sales_analysis' => fn () => GetShopSalesAnalysis::run($shop, $request->only(['from', 'to', 'compareFrom', 'compareTo', 'partners'])),
        ]);
    }
}
