<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 19 Sep 2023 14:12:54 Malaysia Time, Pantai Lembeng, Bali, Indonesia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Actions\Retina\Dropshipping\Api;

use App\Actions\RetinaAction;
use App\Models\Dropshipping\Platform;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;

class ShowApiTokenRetinaDropshipping extends RetinaAction
{
    use AsAction;


    public function handle(ActionRequest $request): array
    {

        $webUser = $request->user();

        $existingToken = $request->user()->tokens()->where('name', 'api-token')->first();

        if ($existingToken) {
            $existingToken->delete();

            $newToken = $request->user()->createToken(
                'api-token',
                ['is_root' => $webUser->is_root ??= false]
            );

            return [
                'token' => $newToken->plainTextToken,
            ];
        }

        $token = $request->user()->createToken(
            'api-token',
            ['is_root' => $webUser->is_root ??= false]
        );

        return [
            'token' => $token->plainTextToken,
        ];
    }


    public function asController(Platform $platform, ActionRequest $request)
    {
        $this->initialisation($request);

        return $this->handle($request);
    }

    public function htmlResponse(array $data, ActionRequest $request): Response
    {
        return Inertia::render(
            'EditModel',
            [
                'title'        => __('API token'),
                'breadcrumbs' => $this->getBreadcrumbs(
                    __('Api')
                ),

                'pageHead'    => [
                    'title'     => 'API token',
                    'icon'      => [
                        'title' => __('Api token'),
                        'icon'  => 'fal fa-key'
                    ],
                ],

                'formData' => [
                    'blueprint' => [
                        [
                            'label'  => __('Token'),
                            'icon'   => 'fa-light fa-fingerprint',
                            'fields' => [
                                'api_token' => [
                                    'type'         => 'input',
                                    'label'        => __('api token'),
                                    'value'        => $data['token'],
                                ],
                                'api_base_sandbox_url' => [
                                    'type'         => 'input',
                                    'label'        => __('url api sandbox'),
                                    'value'        => 'https://app.aiku-sandbox.uk/',
                                ],
                                'api_base_production_url' => [
                                    'type'         => 'input',
                                    'label'        => __('url api production'),
                                    'value'        => 'https://app.aiku.io/',
                                ],
                            ],

                        ],
                    ],
                    'args'      => [
                        'updateRoute' => [
                            'name'       => '',
                            'parameters' => [
                            ]
                        ],
                    ]
                ],

            ]
        );
    }

    public function jsonResponse(array $data): array
    {
        return [
            'token' => $data['token'],
        ];
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
