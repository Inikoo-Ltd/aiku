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
use App\Actions\Traits\WithRetinaAuthRedirect;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;
use App\Actions\Helpers\Country\UI\GetAddressDataForShop;
use App\Actions\Web\Webpage\Iris\ShowIrisWebpage;
use App\Enums\Web\Webpage\WebpageStateEnum;
use App\Http\Resources\CRM\PollsResource;
use App\Models\CRM\Poll;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Arr;

class ShowStandAloneRegistration extends IrisAction
{
    use WithRetinaAuthRedirect;

    public function handle(ActionRequest $request): Response|RedirectResponse
    {
        $shop = $this->shop;
        $polls = Poll::where('shop_id', $shop->id)->where('in_registration', true)->get();
        $pollsResource = PollsResource::collection($polls)->toArray($request);

        $countriesAddressData = GetAddressDataForShop::run($shop, excludeForbiddenBilling: true, excludeForbiddenDelivery: false);

        $webUser = $request->user();

        $website = request()->website;

        $registerPage = $website->registerPage;

        if ($registerPage && $registerPage?->state == WebpageStateEnum::LIVE) {
            $url = ShowIrisWebpage::run('register', [], $request);

            parse_str(parse_url($url, PHP_URL_QUERY) ?? '', $params);

            if ($request->has('ref')) {
                $params['ref'] = $request->query('ref');
            }

            $url = strtok($url, '?') . '?' . http_build_query($params);

            return redirect()->to($url);
        }
        
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

    public function asController(ActionRequest $request): Response|RedirectResponse
    {
        $this->initialisation($request);
        $this->rememberRetinaIntendedUrl($request, $this->website);

        return $this->handle($request);
    }
}
