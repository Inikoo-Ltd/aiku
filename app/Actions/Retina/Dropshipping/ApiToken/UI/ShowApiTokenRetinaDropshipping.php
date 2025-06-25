<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 19 Sep 2023 14:12:54 Malaysia Time, Pantai Lembeng, Bali, Indonesia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Actions\Retina\Dropshipping\ApiToken\UI;

use App\Actions\RetinaAction;
use App\Models\Dropshipping\CustomerSalesChannel;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;

class ShowApiTokenRetinaDropshipping extends RetinaAction
{
    use AsAction;


    public function handle(CustomerSalesChannel $customerSalesChannel, ActionRequest $request): Response
    {
        $env = app()->environment('production')
            ? 'production'
            : 'sandbox';
        $domain = $this->customer->shop?->website?->domain;
        $baseUrl = app()->environment('production')
        ? 'https://v2.' . $domain
        : 'https://canary.' . $domain;

        return Inertia::render(
            'Dropshipping/Api/ApiTokenRetinaDropshipping',
            [
                'title'       => __('API token'),
                'breadcrumbs' => $this->getBreadcrumbs(),

                'pageHead' => [
                    'title' => 'API token',
                    'icon'  => [
                        'title' => __('Api token'),
                        'icon'  => 'fal fa-key'
                    ],
                ],

                'data' => [
                    'api_base_url' => $baseUrl,

                    'redirect_link' => [
                        'message' => __('Generate API token in ') . $env ,
                        'link' => $baseUrl .  '/app/dropshipping/platforms/manual/api/',
                    ],

                    'route_generate' => [
                        'name' => 'retina.dropshipping.customer_sales_channels.api.show.token',
                        'parameters' => [
                            'customerSalesChannel' => $customerSalesChannel->slug,
                        ],
                    ],
                ],
            ]
        );
    }


    public function asController(CustomerSalesChannel $customerSalesChannel, ActionRequest $request): Response
    {
        $this->initialisation($request);

        return $this->handle($customerSalesChannel, $request);
    }


    public function jsonResponse(array $data): array
    {
        return $data;
    }

    public function getBreadcrumbs(): array
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
