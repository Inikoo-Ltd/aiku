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
use App\Actions\Web\Webpage\WithSystemPageRedirect;
use App\Enums\Web\Webpage\WebpageStateEnum;
use App\Http\Resources\CRM\PollsResource;
use App\Models\CRM\Poll;
use Illuminate\Http\Response as HttpResponse;
use Illuminate\Support\Arr;

class ShowStandAloneRegistration extends IrisAction
{
    use WithRetinaAuthRedirect;

    public function handle(ActionRequest $request): Response|HttpResponse
    {
        $shop = $this->shop;
        $polls = Poll::where('shop_id', $shop->id)->where('in_registration', true)->get();
        $pollsResource = PollsResource::collection($polls)->toArray($request);

        $countriesAddressData = GetAddressDataForShop::run($shop, excludeForbiddenBilling: true, excludeForbiddenDelivery: false);

        $webUser = $request->user();

        $website = request()->website;

        $registerPage = $website->registerPage;

        $loggedIn = auth()->check();

        $browserTitle = null;

        $vueFilePath = 'Auth/StandAloneRegistration';
        $webpageData = [
            'countriesAddressData' => $countriesAddressData,
            'defaultCountryId'     => GetRetinaRegistrationDefaultCountry::run($shop, $countriesAddressData, $request),
            'requiresPhoneNumber' => Arr::get($this->shop->settings, 'registration.require_phone_number', false),
            'polls' => $pollsResource,
            'client' => $webUser,
            'registration_settings' => Arr::get($this->shop->settings, 'registration', []),
            'registerRoute' => [
                'name' => 'retina.register_from_standalone.store',
                'method' => 'POST'
            ],
        ];

        if ($registerPage && $registerPage?->state == WebpageStateEnum::LIVE) {
            $tempWebpageData = ShowIrisWebpage::make()->getWebpageData($registerPage->id, [], $loggedIn);

            if ($tempWebpageData) {
                $vueFilePath = 'RetinaWebpage';
                $webpageData = $tempWebpageData;

                $browserTitle            = Arr::get($webpageData, 'webpage_data.title', '');
                $isDifferentWhenLoggedIn = Arr::pull($webpageData, 'is_different_when_logged_in');
            }
        }

        $response =  Inertia::render($vueFilePath, $webpageData);

        if ($browserTitle) {
            $response = $response->withViewData([
                'browserTitle' => $browserTitle,
            ])->toResponse(request());

            $response->headers->set('Cache-Control', 'public, s-maxage=300, max-age=0');
            $response->headers->set('X-Aiku-Cacheable-Inertia', '1');
            $response->headers->set('X-Is-Diff', $isDifferentWhenLoggedIn ? 'Y' : 'N');

            $response->header('X-AIKU-WEBSITE', (string)request()->website->id);
            if (isset($webpageData['webpage_id'])) {
                $response->header('X-AIKU-WEBPAGE', (string)$webpageData['webpage_id']);
            }
        }
        
        return $response;
    }

    public function asController(ActionRequest $request): Response|HttpResponse
    {
        $this->initialisation($request);
        $this->rememberRetinaIntendedUrl($request, $this->website);

        return $this->handle($request);
    }
}
