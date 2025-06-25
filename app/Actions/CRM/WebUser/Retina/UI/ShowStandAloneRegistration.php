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

class ShowStandAloneRegistration extends IrisAction
{
    public function handle(ActionRequest $request): Response|RedirectResponse
    {
        $shop = $this->shop;
        $polls = Poll::where('shop_id', $shop->id)->where('in_registration', true)->get();
        $pollsResource = PollsResource::collection($polls)->toArray($request);


        $webUser = $request->user();
        return Inertia::render(
            'Auth/StandAloneRegistration',
            [
                'countriesAddressData' => GetAddressData::run(),
                'polls' => $pollsResource,
                'client' => $webUser,
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
