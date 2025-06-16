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
use App\Enums\Catalogue\Shop\ShopTypeEnum;
use App\Http\Resources\CRM\PollsResource;
use App\Models\CRM\Poll;
use Illuminate\Http\RedirectResponse;

class ShowStandAloneRegistration extends IrisAction
{


    public function handle(ActionRequest $request): Response|RedirectResponse
    {
        $shop = $this->shop;
        $polls = Poll::where('shop_id', $shop->id)->where('in_registration', true)->where('in_iris', true)->get();
        $pollsResource = PollsResource::collection($polls)->toArray($request);

        if ($shop->type == ShopTypeEnum::FULFILMENT) {
            abort(404);
        }

        $webUser = $request->user();
        return Inertia::render(
            'Auth/StandAloneRegistration',
            [
            'countriesAddressData' => GetAddressData::run(),
            'polls' => $pollsResource,
            'client' => $webUser,
            'registerRoute' => [
                'name' => 'retina.finish_pre_register.store',
                'parameters' => [
                    'shop' => $shop->id
                ],
                'method' => 'POST'
            ],
            'timeline'  => [
                "register" => [
                    "label" => "Register",
                    "tooltip" => "Registered",
                    "key" => "register",
                    "timestamp" => now(),
                ],
                "complete" => [
                    "label" => "Complete Registration",
                    "tooltip" => "Complete Registration",
                    "key" => "complete",
                    "timestamp" => null
                ],
                "finish" => [
                    "label" => "Finish",
                    "tooltip" => "Finished",
                    "key" => "finish",
                    "timestamp" => null
                ],
            ],
            'current_timeline'  => 'complete',
        ]
        );
    }

    public function asController(ActionRequest $request): Response
    {
        $this->initialisation($request);

        return $this->handle($request);
    }


}
