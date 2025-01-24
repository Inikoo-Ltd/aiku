<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 16 Oct 2023 15:27:31 Malaysia Time, Office, Bali, Indonesia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Actions\CRM\WebUser;

use App\Actions\Traits\WithActionUpdate;
use App\Models\CRM\WebUser;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Session;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;
use Lorisleiva\Actions\ActionRequest;
use Symfony\Component\HttpFoundation\Response;

class UpdateRetinaWebUserPasswordViaEmail
{
    use WithActionUpdate;

    private bool $asAction = false;

    public function handle(WebUser $user, array $modelData): WebUser
    {
        data_set($modelData, 'reset_password', false);
        return $this->update($user, $modelData);
    }


    public function rules(): array
    {
        return [
            'password' => ['required', app()->isLocal() || app()->environment('testing') ? null : Password::min(8)],
            'token'    => ['required', 'string'],
            'email'    => ['required', 'email']
        ];
    }

    /**
     * @throws \Illuminate\Validation\ValidationException
     */
    public function asController(ActionRequest $request): void
    {
        $request->validate();

        $this->handle(WebUser::where('email', $request->input('email'))->first(), [
            'password'       => Hash::make($request->input('password')),
            'reset_password' => false
        ]);


    }


    public function htmlResponse(): Response
    {
        Session::put('reloadLayout', '1');
        return Inertia::location(route('retina.dashboard.show'));
    }
}
