<?php
/*
 * Author: Vika Aqordi <aqordivika@yahoo.co.id>
 * Created on: 15-08-2024, Bali, Indonesia
 * Github: https://github.com/aqordeon
 * Copyright: 2024
 *
*/

namespace App\Actions\UI\Shopify\Dashboard;

use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;

class ShowDashboard
{
    use AsAction;


    public function asController(ActionRequest $request): Response
    {

        return Inertia::render('Dashboard/ShopifyDashboard');
    }

    // public function getBreadcrumbs($label = null): array
    // {
    //     return [
    //         [

    //             'type'   => 'simple',
    //             'simple' => [
    //                 'icon'  => 'fal fa-home',
    //                 'label' => $label,
    //                 'route' => [
    //                     'name' => 'retina.dashboard.show'
    //                 ]
    //             ]

    //         ],

    //     ];
    // }
}
