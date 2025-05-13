<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 19 Sep 2023 14:12:54 Malaysia Time, Pantai Lembeng, Bali, Indonesia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Actions\Retina\Dropshipping\Api;

use App\Actions\RetinaAction;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;

class ShowApiTokenRetinaDropshipping extends RetinaAction
{
    use AsAction;


    public function handle(ActionRequest $request): Response
    {
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
                    'api_base_url' => app()->environment('production')
                        ? 'https://app.aiku.io/'
                        : 'https://app.aiku-sandbox.uk/',

                    'route_generate' => [
                        'name' => 'retina.dropshipping.platforms.api.show.token',
                        'parameters' => [
                            'platform' => $this->platform->slug,
                        ],
                    ],
                ],
            ]
        );
    }


    public function asController(ActionRequest $request): Response
    {
        $this->initialisation($request);

        return $this->handle($request);
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
