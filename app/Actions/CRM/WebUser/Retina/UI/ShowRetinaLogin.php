<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 15 Feb 2024 17:08:30 Malaysia Time, Mexico City, Mexico
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\CRM\WebUser\Retina\UI;

use App\Actions\Traits\WithRetinaAuthRedirect;
use App\Actions\Web\Webpage\Iris\ShowIrisWebpage;
use App\Actions\Web\Webpage\WithSystemPageRedirect;
use App\Enums\Catalogue\Shop\ShopTypeEnum;
use App\Enums\Web\Webpage\WebpageStateEnum;
use Illuminate\Http\Response as HttpResponse;
use Illuminate\Support\Arr;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsController;

class ShowRetinaLogin
{
    use AsController;
    use WithRetinaAuthRedirect;


    public function handle(ActionRequest $request): Response|HttpResponse
    {
        $website = request()->website;

        $loginPage = $website->loginPage;
        $loggedIn = auth()->check();

        $browserTitle = null;

        $vueFilePath = 'Auth/RetinaLogin';
        $webpageData = [
            "login_message" => request()->website->shop->type === ShopTypeEnum::DROPSHIPPING ?
                '<p>' . trans('Hey, as you notice we just got a brand new system for our website.') . '</p>
                <p class="py-3">' . trans('You can log in with your old username and password or use your google account to login (if the emails match)') . '.</p>
                <p>' . trans('If the password is not working, you can reset it from the forgot password page and all will be ok.') . '</p>'
                : null,
            'google'    => [
                'client_id' => config('services.google.client_id')
            ]
        ];

        if ($loginPage && $loginPage?->state == WebpageStateEnum::LIVE) {
            $tempWebpageData = ShowIrisWebpage::make()->getWebpageData($loginPage->id, [], $loggedIn);

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
        $this->rememberRetinaIntendedUrl($request, $request->input('website'));

        return $this->handle($request);
    }
}
