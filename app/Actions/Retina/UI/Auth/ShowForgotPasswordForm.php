<?php

/*
 * Author: Artha <artha@aw-advantage.com>
 * Created: Thu, 21 Mar 2024 15:44:09 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Retina\UI\Auth;

use App\Actions\Web\Webpage\Iris\ShowIrisWebpage;
use App\Enums\Web\Webpage\WebpageStateEnum;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response as HttpResponse;
use Illuminate\Support\Arr;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsController;

class ShowForgotPasswordForm
{
    use AsController;

    public function handle(ActionRequest $request): Response|HttpResponse|JsonResponse
    {
        $website = $request->input('website');

        $forgotPasswordPage = $website->forgotPasswordPage;

        $browserTitle = null;

        $vueFilePath = 'Auth/ForgotPasswordForm';
        $webpageData = [
            'back_label' => __('Back to login'),
            'instructions' => __('We will email you a password reset link that will allow you to choose a new one.'),
            'status' => session('status'),
        ];

        $isCustomPage = false;

        if ($forgotPasswordPage && $forgotPasswordPage?->state == WebpageStateEnum::LIVE) {
            if (config('iris.cache.webpage.ttl') == 0) {
                $tempWebpageData = ShowIrisWebpage::make()->getWebpageData($forgotPasswordPage->id, [], false);
            } else {
                $key         = config('iris.cache.webpage.prefix').'_'.$website->id.'_'.('out').'_'.$forgotPasswordPage->id;
                $tempWebpageData = cache()->remember($key, config('iris.cache.webpage.ttl'), function () use ($forgotPasswordPage) {
                    return ShowIrisWebpage::make()->getWebpageData($forgotPasswordPage->id, [], false);
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
        return $this->handle($request);
    }
}
