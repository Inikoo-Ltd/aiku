<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 15 Feb 2024 17:08:30 Malaysia Time, Mexico City, Mexico
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\CRM\WebUser\Retina\UI;

use App\Actions\Traits\WithRetinaAuthRedirect;
use App\Actions\Web\Webpage\Iris\ShowIrisWebpage;
use App\Enums\Catalogue\Shop\ShopTypeEnum;
use App\Enums\Web\Webpage\WebpageStateEnum;
use Illuminate\Http\JsonResponse;
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


    public function handle(ActionRequest $request): Response|HttpResponse|JsonResponse
    {
        $website = $request->input('website');

        $loginPage = $website->loginPage;

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

        $isCustomPage = false;

        if ($loginPage && $loginPage?->state == WebpageStateEnum::LIVE) {
            if (config('iris.cache.webpage.ttl') == 0) {
                $tempWebpageData = ShowIrisWebpage::make()->getWebpageData($loginPage->id, [], false);
            } else {
                $key         = config('iris.cache.webpage.prefix').'_'.$website->id.'_'.('out').'_'.$loginPage->id;
                $tempWebpageData = cache()->remember($key, config('iris.cache.webpage.ttl'), function () use ($loginPage) {
                    return ShowIrisWebpage::make()->getWebpageData($loginPage->id, [], false);
                });
            }

            if (Arr::get($tempWebpageData, 'status', null) !== 'not_found') {
                $vueFilePath = 'RetinaWebpage';
                $webpageData = $tempWebpageData;

                $browserTitle = Arr::get($webpageData, 'webpage_data.title', '');
                $isCustomPage = true;
            }
        }

        $response =  Inertia::render($vueFilePath, $webpageData);

        if ($isCustomPage) {
            $response = $response->withViewData([
                'browserTitle' => $browserTitle,
            ])->toResponse(request());

            $response->headers->set('Cache-Control', 'private, no-store');
            $response->headers->set('X-Is-Diff', 'N');

            $response->header('X-AIKU-WEBSITE', (string)request()->website->id);
            if (isset($webpageData['webpage_id'])) {
                $response->header('X-AIKU-WEBPAGE', (string)$webpageData['webpage_id']);
            }
        }

        return $response;
    }

    public function asController(ActionRequest $request): Response|HttpResponse|JsonResponse
    {
        $this->rememberRetinaIntendedUrl($request, $request->input('website'));

        return $this->handle($request);
    }
}
