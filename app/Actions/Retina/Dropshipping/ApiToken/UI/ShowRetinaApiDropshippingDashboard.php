<?php

/*
 * Author: Ganes <gustiganes@gmail.com>
 * Created on: 09-05-2025, Bali, Indonesia
 * Github: https://github.com/Ganes556
 * Copyright: 2025
 *
*/

namespace App\Actions\Retina\Dropshipping\ApiToken\UI;

use App\Actions\RetinaAction;
use App\Models\Dropshipping\Platform;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;

class ShowRetinaApiDropshippingDashboard extends RetinaAction
{
    use AsAction;


    public function handle(Platform $platform): Response
    {
        return Inertia::render(
            'Dropshipping/Api/RetinaApiDropshippingDashboard',
            [
                'breadcrumbs' => $this->getBreadcrumbs(
                    __('Api Token')
                ),
                'data'       => [
                    'route_generate' => [
                        'name' => 'retina.dropshipping.platforms.api.show.token',
                        'parameters' => [
                            'platform' => $platform->slug,
                        ],
                    ],
                    'route_documentation' => '#',
                    'route_show' => [
                        'name' => 'retina.dropshipping.platforms.api.show',
                        'parameters' => [
                            'platform' => $platform->slug,
                        ],
                    ],
                ],
            ]
        );
    }

    public function asController(Platform $platform, ActionRequest $request): Response|RedirectResponse
    {
        $this->initialisation($request);
        return $this->handle($platform, $request);
    }

    public function getBreadcrumbs($label = null): array
    {
        return [
            [

                'type'   => 'simple',
                'simple' => [
                    'icon'  => 'fal fa-home',
                    'route' => [
                        'name' => 'retina.dashboard.show'
                    ]
                ]

            ],

        ];
    }
}
