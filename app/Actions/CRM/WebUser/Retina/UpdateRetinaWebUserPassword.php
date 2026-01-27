<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 15 Feb 2024 17:08:57 Malaysia Time, Mexico City, Mexico
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\CRM\WebUser\Retina;

use App\Actions\RetinaAction;
use App\Actions\Traits\WithActionUpdate;
use App\Models\CRM\WebUser;
use App\Models\CRM\WebUserPasswordReset;
use Hash;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Support\Facades\Session;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\Validator;
use Lorisleiva\Actions\ActionRequest;

class UpdateRetinaWebUserPassword extends RetinaAction
{
    use WithActionUpdate;


    public function handle(array $modelData): WebUser
    {
        $webUserPasswordReset = WebUserPasswordReset::find($modelData['web_user_password_reset_id']);
        $webUser              = $webUserPasswordReset->webUser;

        $webUserPasswordReset->delete();
        data_forget($modelData, 'web_user_password_reset_id');
        data_forget($modelData, 'token');
        data_set($modelData, 'password', Hash::make($modelData['password']));
        data_set($modelData, 'auth_type', 'default');
        data_set($modelData, 'reset_password', false);

        return $this->update($webUser, $modelData, 'settings');
    }


    public function rules(): array
    {
        return [
            'token'                      => ['required', 'string', 'min:24', 'max:28'],
            'web_user_password_reset_id' => ['required', 'integer', Rule::exists('web_user_password_resets', 'id')->where('website_id', $this->website->id)],
            'password'                   => ['required', app()->isLocal() || app()->environment('testing') ? null : Password::min(8)],
        ];
    }

    public function afterValidator(Validator $validator, ActionRequest $request): void
    {
        $webUserPasswordReset = WebUserPasswordReset::find($request->input('web_user_password_reset_id'));

        if (!$webUserPasswordReset) {
            $validator->errors()->add('password', 'Token not found.');
        } else {
            if (!Hash::check($this->get('token'), $webUserPasswordReset->token)) {
                $validator->errors()->add('password', 'Invalid token.');
            }

            if ($webUserPasswordReset->created_at->addMinutes(35)->isPast()) {
                $validator->errors()->add('password', __('Token expired, the token is only valid for 30 minutes.'));
            }
        }
    }


    public function asController(ActionRequest $request): WebUser
    {
        $this->logoutInitialisation($request);

        return $this->handle($this->validatedData);
    }


    public function htmlResponse(WebUser $webUser): RedirectResponse
    {
        Session::put('reloadLayout', '1');
        Auth::guard('retina')->login($webUser);

        return Redirect::route('retina.dashboard.show');
    }
}
