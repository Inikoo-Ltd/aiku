<?php
/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 06 Mar 2023 18:43:21 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Actions\UI\CRM;

use App\Actions\OrgAction;
use App\Actions\SysAdmin\Organisation\UI\ShowOrganisationDashboard;
use App\Models\Fulfilment\Fulfilment;
use App\Models\Market\Shop;
use App\Models\SysAdmin\Organisation;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;

class ShowShopCRMDashboard extends OrgAction
{
    public function handle(Shop $shop): Shop
    {
        return $shop;
    }


    public function authorize(ActionRequest $request): bool
    {
        return $request->user()->hasPermissionTo("crm.{$this->shop->id}.view");
    }


    /** @noinspection PhpUnusedParameterInspection */
    public function inShop(Organisation $organisation, Shop $shop, ActionRequest $request): Shop
    {
        $this->initialisationFromShop($shop, $request);

        return $this->handle($shop);
    }


    public function htmlResponse(Shop $shop, ActionRequest $request): Response
    {
        $container = [
            'icon'    => ['fal', 'fa-store-alt'],
            'tooltip' => __('Shop'),
            'label'   => Str::possessive($shop->name)
        ];


        $routeParameters = $request->route()->originalParameters();

        return Inertia::render(
            'Org/Shop/CRM/CRMDashboard',
            [
                'breadcrumbs' => $this->getBreadcrumbs(
                    $request->route()->originalParameters()
                ),
                'title'       => 'CRM',
                'pageHead'    => [
                    'title'     => __('customer relationship manager'),
                    'container' => $container
                ],
                'stats'       => [
                    'customers' => [
                        'name' => __('customers'),
                        'stat' => $shop->crmStats->number_customers,

                        'href' =>
                            [
                                'name'       => 'grp.org.shops.crm.customers.index',
                                'parameters' => $routeParameters
                            ]

                    ],
                    'prospects' => [
                        'name' => __('prospects'),
                        'stat' => $shop->crmStats->number_prospects,
                        'href' =>
                            [
                                'name'       => 'grp.org.shops.crm.prospects.index',
                                'parameters' => array_merge($routeParameters, [
                                    '_query' => [
                                        'tab' => 'prospects'
                                    ]
                                ])
                            ]
                    ]
                ]
            ]
        );
    }


    public function getBreadcrumbs(array $routeParameters): array
    {

        $fulfilment=Fulfilment::where('slug', $routeParameters['fulfilment'])->first();

        return array_merge(
            ShowOrganisationDashboard::make()->getBreadcrumbs($routeParameters),
            [
                [
                    'type'   => 'simple',
                    'simple' => [
                        'route' => [
                            'name'       => 'grp.org.fulfilment.shops.show.crm.dashboard',
                            'parameters' => $routeParameters
                        ],
                        'label' => __('CRM').' ('.$fulfilment->shop->code.')',
                    ]
                ]
            ]
        );
    }

}
