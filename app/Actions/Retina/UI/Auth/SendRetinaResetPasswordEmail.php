<?php

/*
 * Author: Artha <artha@aw-advantage.com>
 * Created: Thu, 21 Mar 2024 15:44:04 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Retina\UI\Auth;

use App\Actions\Comms\Email\SendResetPasswordEmail;
use App\Actions\CRM\WebUserPasswordReset\StoreWebUserPasswordReset;
use App\Actions\RetinaAction;
use App\Models\CRM\WebUser;
use Closure;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsController;

class SendRetinaResetPasswordEmail extends RetinaAction
{
    use AsController;


    public function handle(array $modelData): void
    {
        $webUser = WebUser::where('website_id', $this->website->id)
            ->whereRaw('lower(email) = ?', [strtolower($modelData['email'])])
            ->first();

        $token = Str::random(rand(24, 28));
        $webUserPasswordReset = StoreWebUserPasswordReset::run($webUser, $token);

        $url = route('retina.reset-password.show', [
            'token' => $token,
            'id' => $webUserPasswordReset->id
        ]);


        SendResetPasswordEmail::run($webUser, [
            'url' => $url
        ]);


    }

    public function create(): Response
    {
        return Inertia::render('Auth/ForgotPassword', [
            'status' => session('status'),
        ]);
    }

    public function getValidationMessages(): array
    {
        return [
            'email.exists' => __("We can't find a user with that email address."),
        ];
    }

    public function rules(): array
    {
        return [
            'email' => [
                'required',
                'email',
                function (string $attribute, mixed $value, Closure $fail): void {
                    $exists = WebUser::where('website_id', $this->website->id)
                        ->where('status', true)
                        ->whereRaw('lower(email) = ?', [strtolower((string) $value)])
                        ->exists();

                    if (! $exists) {
                        $fail(__("We can't find a user with that email address."));
                    }
                },
            ],
        ];
    }


    public function asController(ActionRequest $request): void
    {
        $this->logoutInitialisation($request);

        $this->handle($this->validatedData);
    }
}
