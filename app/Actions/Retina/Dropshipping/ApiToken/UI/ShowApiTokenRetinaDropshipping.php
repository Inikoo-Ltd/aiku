<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 19 Sep 2023 14:12:54 Malaysia Time, Pantai Lembeng, Bali, Indonesia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Actions\Retina\Dropshipping\ApiToken\UI;

use App\Actions\RetinaAction;
use App\Models\Dropshipping\Platform;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;

class ShowApiTokenRetinaDropshipping extends RetinaAction
{
    use AsAction;


    public function handle(Platform $platform, ActionRequest $request): Response
    {
        $env = app()->environment('production')
            ? 'production'
            : 'sandbox';
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
                        ? 'https://v2.aw-dropship.com/'
                        : 'https://canary.aw-dropship.com/',

                    'redirect_link' => [
                        'message' => __('Generate API token in ') . $env ,
                        'link' => $env == 'production' ? 'https://canary.aw-dropship.com/app/dropshipping/platforms/manual/api/' : 'https://v2.aw-dropship.com/app/dropshipping/platforms/manual/api/',
                    ],

                    'route_generate' => [
                        'name' => 'retina.dropshipping.platforms.api.show.token',
                        'parameters' => [
                            'platform' => $platform->slug,
                        ],
                    ],
                ],
            ]
        );
    }


    public function asController(Platform $platform, ActionRequest $request): Response
    {
        $this->initialisation($request);

        return $this->handle($platform, $request);
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
