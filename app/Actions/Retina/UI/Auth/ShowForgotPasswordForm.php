<?php

/*
 * Author: Artha <artha@aw-advantage.com>
 * Created: Thu, 21 Mar 2024 15:44:09 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Retina\UI\Auth;

use App\Actions\Web\Webpage\Iris\ShowIrisWebpage;
use App\Enums\Web\Webpage\WebpageStateEnum;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsController;

class ShowForgotPasswordForm
{
    use AsController;

    public function handle(ActionRequest $request): Response|RedirectResponse
    {
        $website = request()->website;

        $forgotPasswordPage = $website->forgotPasswordPage;

        if ($forgotPasswordPage && $forgotPasswordPage?->state == WebpageStateEnum::LIVE) {
            $url = ShowIrisWebpage::run('forgot-password', [], $request);

            parse_str(parse_url($url, PHP_URL_QUERY) ?? '', $params);

            if ($request->has('ref')) {
                $params['ref'] = $request->query('ref');
            }

            $url = strtok($url, '?') . '?' . http_build_query($params);

            return redirect()->to($url);
        }

        return Inertia::render('Auth/ForgotPasswordForm', [
            'back_label' => __('Back to login'),
            'instructions' => __('We will email you a password reset link that will allow you to choose a new one.'),
            'status' => session('status'),
        ]);
    }

    public function asController(ActionRequest $request): Response|RedirectResponse
    {
        return $this->handle($request);
    }
}
