<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Iris\Json;

use App\Actions\Helpers\Country\UI\GetAddressDataForShop;
use App\Actions\IrisAction;
use App\Http\Resources\CRM\PollsResource;
use App\Models\CRM\Poll;
use Illuminate\Support\Arr;
use Lorisleiva\Actions\ActionRequest;

class GetIrisRegistrationData extends IrisAction
{
    public function handle(ActionRequest $request): array
    {
        $shop = $this->shop;

        $polls = Poll::where('shop_id', $shop->id)->where('in_registration', true)->get();

        return [
            'countriesAddressData'  => GetAddressDataForShop::run($shop, excludeForbiddenBilling: true, excludeForbiddenDelivery: false),
            'requiresPhoneNumber'   => Arr::get($shop->settings, 'registration.require_phone_number', false),
            'polls'                 => PollsResource::collection($polls)->toArray($request),
            'client'                => $request->user(),
            'registration_settings' => Arr::get($shop->settings, 'registration', []),
            'shop_type'             => $shop->type->value,
        ];
    }

    public function asController(ActionRequest $request): array
    {
        $this->initialisation($request);

        return $this->handle($request);
    }
}
