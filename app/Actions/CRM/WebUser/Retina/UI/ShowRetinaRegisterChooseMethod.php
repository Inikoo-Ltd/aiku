<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 16 Jun 2025 15:18:47 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\CRM\WebUser\Retina\UI;

use App\Actions\IrisAction;
use App\Actions\Traits\WithRetinaAuthRedirect;
use App\Actions\Web\Webpage\Iris\ShowIrisWebpage;
use App\Enums\Web\Webpage\WebpageStateEnum;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response as HttpResponse;
use Illuminate\Support\Arr;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;

class ShowRetinaRegisterChooseMethod extends IrisAction
{
    use WithRetinaAuthRedirect;

    public function handle(ActionRequest $request): Response|HttpResponse|JsonResponse
    {
        $website = $request->input('website');

        $registerDashboardPage = $website->registerDashboardPage;

        $browserTitle = null;

        $google = [
            'client_id' => config('services.google.client_id')
        ];

        $vueFilePath = 'Auth/RegisterSelectMethod';
        $webpageData = [
            'google' => $google
        ];

        $isCustomPage = false;

        if ($registerDashboardPage && $registerDashboardPage->state == WebpageStateEnum::LIVE) {
            if (config('iris.cache.webpage.ttl') == 0) {
                $tempWebpageData = ShowIrisWebpage::make()->getWebpageData($registerDashboardPage->id, [], false);
            } else {
                $key             = config('iris.cache.webpage.prefix').'_'.$website->id.'_'.('out').'_'.$registerDashboardPage->id;
                $tempWebpageData = cache()->remember($key, config('iris.cache.webpage.ttl'), function () use ($registerDashboardPage) {
                    return ShowIrisWebpage::make()->getWebpageData($registerDashboardPage->id, [], false);
                });
            }

            if (Arr::get($tempWebpageData, 'status', null) === 'ok') {
                $vueFilePath = 'RetinaWebpage';
                $webpageData = array_merge($tempWebpageData, ['google' => $google]);

                $browserTitle = Arr::get($webpageData, 'webpage_data.title', '');
                $isCustomPage = true;
            }
        }

        $response = Inertia::render($vueFilePath, $webpageData);

        if ($isCustomPage) {
            $response = $response->withViewData([
                'browserTitle' => $browserTitle,
            ])->toResponse(request());

            $response->headers->set('Cache-Control', 'private, no-store');
            $response->headers->set('X-Is-Diff', 'N');

            $response->header('X-AIKU-WEBSITE', (string)$website->id);
            if (isset($webpageData['webpage_id'])) {
                $response->header('X-AIKU-WEBPAGE', (string)$webpageData['webpage_id']);
            }
        }

        return $response;
    }


    public function asController(ActionRequest $request): Response|HttpResponse|JsonResponse
    {
        $this->initialisation($request);
        $this->rememberRetinaIntendedUrl($request, $this->website);

        return $this->handle($request);
    }

}
