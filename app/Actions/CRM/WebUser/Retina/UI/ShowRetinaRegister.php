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

class ShowRetinaRegister
{
    use AsController;


    public function handle(ActionRequest $request): Response
    {
        $shop = $request->website->shop;

        if ($shop->type == ShopTypeEnum::FULFILMENT) {
            return Inertia::render(
                'Auth/Register',
                [
                'countriesAddressData' => GetAddressData::run(),
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
                    'name' => 'retina.ds.register.store',
                    'parameters' => [
                        'shop' => $shop->id
                    ]
                ]
            ]
            );
        }


    }

}
