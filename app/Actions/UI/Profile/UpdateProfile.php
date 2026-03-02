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
use App\Models\Helpers\Language;
use App\Models\SysAdmin\User;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Facades\Session;
use Illuminate\Validation\Rules\File;
use Illuminate\Validation\Rules\Password;
use Lorisleiva\Actions\ActionRequest;

class UpdateProfile extends OrgAction
{
    use WithActionUpdate;
    use WithProfile;

    public function handle(User $user, array $modelData): User
    {
        if (Arr::exists($modelData, 'notification_settings')) {
            $notificationSettings = Arr::pull($modelData, 'notification_settings');

            if (is_array($notificationSettings)) {
                foreach ($notificationSettings as $row) {
                    if (!is_array($row)) {
                        continue;
                    }

                    $id = Arr::get($row, 'id');
                    if (!$id) {
                        continue;
                    }

                    $setting = $user->notificationSettings()->whereKey($id)->first();
                    if (!$setting) {
                        continue;
                    }

                    $isEnabled = (bool) Arr::get($row, 'is_enabled', false);
                    $filters = Arr::get($row, 'filters', []);

                    if (!is_array($filters)) {
                        $filters = [];
                    }

                    if (array_key_exists('state', $filters)) {
                        if (!is_array($filters['state']) || count($filters['state']) === 0) {
                            unset($filters['state']);
                        }
                    }

                    foreach ($filters as $key => $value) {
                        if ($value === null || $value === '' || (is_array($value) && count($value) === 0)) {
                            unset($filters[$key]);
                        }
                    }

                    if (count($filters) === 0) {
                        $filters = [];
                    }

                    $setting->update([
                        'is_enabled' => $isEnabled,
                        'filters' => $filters,
                    ]);
                }
            }
        }

        if (Arr::exists($modelData, 'hide_logo')) {
            $hideLogo                           = Arr::pull($modelData, 'hide_logo');
            $modelData['settings']['hide_logo'] = $hideLogo;
        }

        if (Arr::exists($modelData, 'timezones')) {
            $timezones                           = Arr::pull($modelData, 'timezones');
            $modelData['settings']['timezones'] = $timezones;
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
        data_forget($modelData, 'image');

        $user = $this->update($user, $modelData, ['settings']);

        $changes = $user->getChanges();
        if (Arr::has($changes, 'language_id')) {
            $language = Language::find($user->language_id);
            $locale   = $language->code;
            app()->setLocale($locale);
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
            'language_id'       => ['sometimes', 'required', 'exists:languages,id'],
            'app_theme'         => ['sometimes', 'required'],
            'hide_logo'         => ['sometimes', 'boolean'],
            'preferred_printer' => ['sometimes', 'integer'],
            'image'             => [
                'sometimes',
                'nullable',
                File::image()
                    ->max(12 * 1024)
            ],
            'timezones'         => ['sometimes', 'array'],
            'enable_2fa'        => ['sometimes', 'array'],
            'notification_settings' => ['sometimes', 'array'],
            'notification_settings.*.id' => ['required', 'integer', 'exists:user_notification_settings,id'],
            'notification_settings.*.is_enabled' => ['required', 'boolean'],
            'notification_settings.*.filters' => ['nullable', 'array'],
            'notification_settings.*.filters.state' => ['nullable', 'array'],
            'notification_settings.*.filters.state.*' => ['string'],
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
