<?php

/*
 * Author: Ganes <gustiganes@gmail.com>
 * Created on: 02-06-2025, Bali, Indonesia
 * Github: https://github.com/Ganes556
 * Copyright: 2025
 *
*/

namespace App\Actions\CRM\WebUser\Retina\UI;

use App\Actions\IrisAction;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;
use App\Actions\Helpers\Country\UI\GetAddressData;
use App\Http\Resources\CRM\PollsResource;
use App\Models\CRM\Poll;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Arr;

class ShowStandAloneRegistration extends IrisAction
{
    public function handle(ActionRequest $request): Response|RedirectResponse
    {
        $shop = $this->shop;
        $polls = Poll::where('shop_id', $shop->id)->where('in_registration', true)->get();
        $pollsResource = PollsResource::collection($polls)->toArray($request);

        $bannedCountries = array_keys(array_filter($shop->banned_country_regions, fn ($item) => $item['billing'] && empty($item['postcode'])));

        $countriesAddressData = array_filter(
            GetAddressData::run($shop, true), fn (array $country) => !in_array($country['code'], $bannedCountries),
        );

        $webUser = $request->user();
        return Inertia::render(
            'Auth/StandAloneRegistration',
            [
                'countriesAddressData' => $countriesAddressData,
                'requiresPhoneNumber' => Arr::get($this->shop->settings, 'registration.require_phone_number', false),
                'polls' => $pollsResource,
                'client' => $webUser,
                'registration_settings' => Arr::get($this->shop->settings, 'registration', []),
                'registerRoute' => [
                    'name' => 'retina.register_from_standalone.store',
                    'method' => 'POST'
                ],
            ]
        );
    }

    public function asController(ActionRequest $request): Response
    {
        $this->initialisation($request);

        return $this->handle($request);
    }
}
