<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 15 Feb 2024 17:08:30 Malaysia Time, Mexico City, Mexico
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\CRM\WebUser\Retina\UI;

use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsController;
use App\Actions\Helpers\Country\UI\GetAddressData;
use App\Enums\Catalogue\Shop\ShopTypeEnum;
use App\Http\Resources\CRM\PollsResource;
use App\Models\CRM\Poll;

class ShowRetinaRegister
{
    use AsController;


    public function handle(ActionRequest $request): Response
    {
        $shop = $request->website->shop;
        $polls = Poll::where('shop_id', $shop->id)->where('in_registration', true)->where('in_iris', true)->get();
        $pollsResource = PollsResource::collection($polls)->toArray($request);

        if ($shop->type == ShopTypeEnum::FULFILMENT) {
            return Inertia::render(
                'Auth/Register',
                [
                'countriesAddressData' => GetAddressData::run(),
                'polls' => $pollsResource,
                'registerRoute' => [
                    'name' => 'retina.register.store',
                    'parameters' => [
                        'fulfilment' => $shop->fulfilment->id
                    ]
                ]
            ]
            );
        } else {
            return Inertia::render(
                'Auth/DropshipRegister',
                [
                    'countriesAddressData' => GetAddressData::run(),
                    'registerRoute' => [
                        'name' => 'register_pre_customer.store',
                        'parameters' => [
                            'shop' => $shop->id
                        ]
                    ],
                    'google'    => [
                        'client_id' => config('services.google.client_id')
                    ]
                ]
            );
        }


    }

}
