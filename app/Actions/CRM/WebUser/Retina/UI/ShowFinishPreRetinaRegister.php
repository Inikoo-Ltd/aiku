<?php

/*
 * Author: Ganes <gustiganes@gmail.com>
 * Created on: 02-06-2025, Bali, Indonesia
 * Github: https://github.com/Ganes556
 * Copyright: 2025
 *
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
use Illuminate\Http\RedirectResponse;

class ShowFinishPreRetinaRegister
{
    use AsController;


    public function handle(ActionRequest $request): Response|RedirectResponse
    {
        $shop = $request->website->shop;
        $polls = Poll::where('shop_id', $shop->id)->where('in_registration', true)->where('in_iris', true)->get();
        $pollsResource = PollsResource::collection($polls)->toArray($request);

        if ($shop->type == ShopTypeEnum::FULFILMENT) {
            abort(404);
        }

        $webUser = $request->user();
        return Inertia::render(
            'Auth/DropshipFinishRegistration',
            [
            'countriesAddressData' => GetAddressData::run(),
            'polls' => $pollsResource,
            'timeline' => [],
            'current_timeline' => null,
            'client' => $webUser,
            'registerRoute' => [
                'name' => 'retina.finish_pre_register.store',
                'parameters' => [
                    'shop' => $shop->id
                ],
                'method' => 'POST'
            ]
        ]
        );
    }

}
