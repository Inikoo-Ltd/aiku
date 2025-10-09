<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 18 Dec 2024 22:38:08 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Billables\UI;

use App\Actions\Catalogue\Shop\UI\ShowShop;
use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\WithCatalogueAuthorisation;
use App\Enums\Billables\UI\BillablesTabsEnum;
use App\Models\Catalogue\Shop;
use App\Models\SysAdmin\Organisation;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;

class ShowBillablesDashboard extends OrgAction
{
    use WithCatalogueAuthorisation;



    public function asController(Organisation $organisation, Shop $shop, ActionRequest $request): ActionRequest
    {
        $this->initialisationFromShop($shop, $request);

        return $request;
    }


    public function htmlResponse(ActionRequest $request): Response
    {
        return Inertia::render(
            'Org/Billables/BillablesDashboard',
            [
                'breadcrumbs'  => $this->getBreadcrumbs($request->route()->originalParameters()),
                'title'        => __('billables'),
                'pageHead'     => [
                    'icon'      => [
                        'icon'  => ['fal', 'fa-ballot'],
                        'title' => __('Billables')
                    ],
                    'iconRight' => [
                        'icon'  => ['fal', 'fa-chart-network'],
                        'title' => __('Billables')
                    ],
                    'title' => __('Billables dashboard'),
                ],
                'tabs' => [
                    'current'    => $this->tab,
                    'navigation' => BillablesTabsEnum::navigation()
                ],


            ]
        );
    }

    public function getBreadcrumbs(array $routeParameters): array
    {
        return
           array_merge(
               ShowShop::make()->getBreadcrumbs($routeParameters),
               [
                    [
                        'type'   => 'simple',
                        'simple' => [
                            'route' => [
                                'name'       => 'grp.org.shops.show.billables.dashboard',
                                'parameters' => $routeParameters
                            ],
                            'label' => __('Billables')
                        ]
                    ]
                ]
           );
    }


}
