<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 16 Jun 2025 15:18:47 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\CRM\WebUser\Retina\UI;

use App\Actions\IrisAction;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;
use App\Actions\Helpers\Country\UI\GetAddressData;
use App\Enums\Catalogue\Shop\ShopTypeEnum;
use App\Http\Resources\CRM\PollsResource;
use App\Models\CRM\Poll;

class ShowRetinaRegisterChooseMethod extends IrisAction
{
    public function handle(ActionRequest $request): Response
    {
        $shop = $this->shop;

        if ($shop->type == ShopTypeEnum::FULFILMENT) {
            $polls = Poll::where('shop_id', $shop->id)->where('in_registration', true)->where('in_iris', true)->get();
            $pollsResource = PollsResource::collection($polls)->toArray($request);

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
                'Auth/DropshippingRegisterSelectMethod',
                [
                    'countriesAddressData' => GetAddressData::run(),
                    'registerRoute' => [
                        'name' => 'retina.register_pre_customer.store',
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

    public function asController(ActionRequest $request): Response
    {
        $this->initialisation($request);

        return $this->handle($request);
    }

}
