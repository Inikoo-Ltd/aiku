<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 24 Apr 2023 20:22:54 Malaysia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Actions\UI\Profile;

use App\Actions\OrgAction;
use App\Actions\Traits\UI\WithProfile;
use App\Actions\Traits\WithActionUpdate;
use App\Actions\UI\Grp\BreakUserUiProps;
use App\Models\Helpers\Language;
use App\Models\Helpers\Timezone;
use App\Models\SysAdmin\User;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Facades\Session;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\File;
use Illuminate\Validation\Rules\Password;
use Lorisleiva\Actions\ActionRequest;

class UpdateProfile extends OrgAction
{
    use WithActionUpdate;
    use WithProfile;

    public function handle(User $user, array $modelData): User
    {
        if (Arr::exists($modelData, 'nickname')) {
            $nickname               = trim((string) $modelData['nickname']);
            $modelData['nickname'] = $nickname === '' ? null : $nickname;
        }

        if (Arr::exists($modelData, 'hide_logo')) {
            $hideLogo                           = Arr::pull($modelData, 'hide_logo');
            $modelData['settings']['hide_logo'] = $hideLogo;
        }

        if (Arr::exists($modelData, 'timezone')) {
            $timezoneName            = Arr::pull($modelData, 'timezone');
            $modelData['timezone_id'] = $timezoneName ? Timezone::where('name', $timezoneName)->value('id') : null;
        }

        if (Arr::exists($modelData, 'preferred_printer')) {
            $printerId                                     = Arr::pull($modelData, 'preferred_printer');
            $modelData['settings']['preferred_printer_id'] = $printerId;
        }

        if ($twoFa = Arr::pull($modelData, 'enable_2fa')) {
            if (data_get($twoFa, 'has_2fa')) {
                data_set($modelData, 'google2fa_secret', data_get($twoFa, 'secretKey'));
            } else {
                // Remove from DB if it is false
                data_set($modelData, 'google2fa_secret', null);
            }
        }

        $user = $this->processProfileAvatar($modelData, $user);
        if (Arr::exists($modelData, 'app_theme')) {
            $appTheme                           = Arr::pull($modelData, 'app_theme');
            $modelData['settings']['app_theme'] = $appTheme;
        }

        if (Arr::exists($modelData, 'chat_theme')) {
            $chatTheme                           = Arr::pull($modelData, 'chat_theme');
            $modelData['settings']['chat_theme'] = $chatTheme;
        }
        data_forget($modelData, 'image');

        $languageWasSubmitted = Arr::has($modelData, 'language_id');

        $user = $this->update($user, $modelData, ['settings']);

        $changes = $user->getChanges();
        if (Arr::hasAny($changes, ['timezone_id', 'settings'])) {
            BreakUserUiProps::run($user);
        }

        /*
         * Deliberately keyed on the language being submitted rather than on it changing: when
         * cached props hold the wrong language, picking the language the account is already set
         * to is a user's only way out, and gating this on a change made that a silent no-op.
         */
        if ($languageWasSubmitted) {
            $language = Language::find($user->language_id);
            $locale   = $language->code;
            app()->setLocale($locale);
            BreakUserUiProps::run($user);
            Cookie::queue('aiku_language', $locale, 60 * 8);
            Session::put('aiku_language', $locale);
            Session::put('reloadLayout', '1');
        }


        return $user;
    }


    public function rules(): array
    {
        return [
            'password'          => ['sometimes', 'required', app()->isLocal() || app()->environment('testing') ? null : Password::min(8)],
            'email'             => 'sometimes|required|email|unique:App\Models\SysAdmin\User,email,'.request()->user()->id,
            'about'             => ['sometimes', 'nullable', 'string', 'max:255'],
            'nickname'          => ['sometimes', 'nullable', 'string', 'min:2', 'max:24', 'regex:/^[\pL\pN ._-]+$/u', Rule::unique('users', 'nickname')->ignore(request()->user()->id)],
            'language_id'       => ['sometimes', 'required', 'exists:languages,id'],
            'app_theme'         => ['sometimes', 'required'],
            'chat_theme'        => ['sometimes', 'nullable', Rule::in(['light', 'sky', 'blush', 'sand', 'mint', 'dracula', 'nord', 'gruvbox', 'monokai', 'onedark', 'solarized'])],
            'hide_logo'         => ['sometimes', 'boolean'],
            'preferred_printer' => ['sometimes', 'integer'],
            'image'             => [
                'sometimes',
                'nullable',
                File::image()
                    ->max(12 * 1024)
            ],
            'timezone'          => ['sometimes', 'nullable', 'exists:timezones,name'],
            'enable_2fa'        => ['sometimes', 'array'],
            'settings'          => ['sometimes'],
        ];
    }


    public function asController(ActionRequest $request): User
    {
        $this->initialisationFromGroup(app('group'), $request);

        return $this->handle($request->user(), $this->validatedData);
    }

    public function asAction(User $user, array $modelData): User
    {
        $this->asAction = true;
        $this->initialisationFromGroup(app('group'), $modelData);

        return $this->handle($user, $this->validatedData);
    }

}
