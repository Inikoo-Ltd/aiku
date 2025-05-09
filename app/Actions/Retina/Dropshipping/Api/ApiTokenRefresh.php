<?php

/*
 * Author: Ganes <gustiganes@gmail.com>
 * Created on: 09-05-2025, Bali, Indonesia
 * Github: https://github.com/Ganes556
 * Copyright: 2025
 *
*/

namespace App\Actions\Retina\Dropshipping\Api;

use App\Actions\RetinaAction;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;

class ApiTokenRefresh extends RetinaAction
{
    use AsAction;


    public function handle(ActionRequest $request): array
    {

        $customer = $request->user()->customer;

        $existingToken = $customer->tokens()->where('name', 'api-token')->first();

        if ($existingToken) {
            $existingToken->delete();

            $newToken = $customer->createToken('api-token', ['retina']);

            return [
                'token' => $newToken->plainTextToken,
            ];
        }

        $token = $customer->createToken('api-token', ['retina']);

        return [
            'token' => $token->plainTextToken,
        ];
    }


    public function asController(ActionRequest $request)
    {
        $this->initialisation($request);

        return $this->handle($request);
    }

    public function jsonResponse(array $data): array
    {
        return $data;
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
