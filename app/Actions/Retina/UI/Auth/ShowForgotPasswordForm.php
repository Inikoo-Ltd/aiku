<?php

/*
 * Author: Artha <artha@aw-advantage.com>
 * Created: Thu, 21 Mar 2024 15:44:09 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Retina\UI\Auth;

use App\Actions\Web\Webpage\Iris\ShowIrisWebpage;
use App\Enums\Web\Webpage\WebpageStateEnum;
use Illuminate\Http\Response as HttpResponse;
use Illuminate\Support\Arr;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsController;

class ShowForgotPasswordForm
{
    use AsController;

    public function handle(ActionRequest $request): Response|HttpResponse
    {
        $website = request()->website;

        $forgotPasswordPage = $website->forgotPasswordPage;

        $loggedIn = auth()->check();

        $browserTitle = null;

        $vueFilePath = 'Auth/ForgotPasswordForm';
        $webpageData = [
            'back_label' => __('Back to login'),
            'instructions' => __('We will email you a password reset link that will allow you to choose a new one.'),
            'status' => session('status'),
        ];

        if ($forgotPasswordPage && $forgotPasswordPage?->state == WebpageStateEnum::LIVE) {
            $tempWebpageData = ShowIrisWebpage::make()->getWebpageData($forgotPasswordPage->id, [], $loggedIn);

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
        return $this->handle($request);
    }
}
