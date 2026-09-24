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
use App\Enums\SysAdmin\User\UserNotificationEnum;
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

    private const array ALERT_SOUND_KINDS = ['chat', 'whatsapp', 'email', 'colleague'];

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

        if (Arr::exists($modelData, 'notifications')) {
            $modelData['settings']['notifications'] = collect(UserNotificationEnum::cases())
                ->mapWithKeys(fn (UserNotificationEnum $event) => [
                    $event->value => array_values(array_intersect(Arr::get($modelData, 'notifications.'.$event->value, []), UserNotificationEnum::CHANNELS)),
                ])->all();
            data_forget($modelData, 'notifications');
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

        $avatarBeforeUpdate = $user->image_id;
        $user               = $this->processProfileAvatar($modelData, $user);
        $avatarWasChanged   = $user->image_id !== $avatarBeforeUpdate;

        if (Arr::exists($modelData, 'app_theme')) {
            $appTheme                           = Arr::pull($modelData, 'app_theme');
            $modelData['settings']['app_theme'] = $appTheme;
        }

        if (Arr::exists($modelData, 'stale_orders_days')) {
            $modelData['settings']['stale_orders_days'] = max(1, (int) Arr::pull($modelData, 'stale_orders_days'));
        }

        if (Arr::exists($modelData, 'stale_orders_filters')) {
            $modelData['settings']['stale_orders_filters'] = Arr::pull($modelData, 'stale_orders_filters');
        }

        if (Arr::exists($modelData, 'tickets_list_mine')) {
            $modelData['settings']['tickets_list_mine'] = (string) Arr::pull($modelData, 'tickets_list_mine');
        }

        foreach (['ticket_comments_newest_first', 'ticket_history_newest_first'] as $ticketOrderSetting) {
            if (Arr::exists($modelData, $ticketOrderSetting)) {
                $modelData['settings'][$ticketOrderSetting] = (bool) Arr::pull($modelData, $ticketOrderSetting);
            }
        }

        if (Arr::exists($modelData, 'alert_sounds')) {
            $modelData['settings']['alert_sounds'] = Arr::only(Arr::pull($modelData, 'alert_sounds'), self::ALERT_SOUND_KINDS);
        }

        if (Arr::exists($modelData, 'alert_preview_seconds')) {
            $modelData['settings']['alert_preview_seconds'] = (int) Arr::pull($modelData, 'alert_preview_seconds');
        }

        $organisationColoursWereSubmitted = Arr::exists($modelData, 'org_themes');

        if ($organisationColoursWereSubmitted) {
            $orgThemes                           = Arr::pull($modelData, 'org_themes');
            $modelData['settings']['org_themes'] = [
                'enabled' => (bool) Arr::get($orgThemes, 'enabled', false),
                'themes'  => $this->sanitiseOrganisationThemes($user, Arr::get($orgThemes, 'themes', [])),
            ];
        }

        if (Arr::exists($modelData, 'chat_theme')) {
            $chatTheme                           = Arr::pull($modelData, 'chat_theme');
            $modelData['settings']['chat_theme'] = $chatTheme;
        }

        if (Arr::exists($modelData, 'chat_signature')) {
            $signature = Arr::pull($modelData, 'chat_signature');
            if ($user->chatAgent) {
                $user->chatAgent->update(['signature' => $signature]);
            }
        }

        data_forget($modelData, 'image');

        $languageWasSubmitted = Arr::has($modelData, 'language_id');

        $user = $this->update($user, $modelData, ['settings']);

        $changes = $user->getChanges();

        /*
         * The avatar is saved by SaveModelImage before the update above, so image_id never appears
         * in getChanges() and has to be tracked on its own. Without the recache the cached first
         * load props keep the old avatar_thumbnail for the rest of their TTL, and without
         * reloadLayout the next Inertia request would not ship those props until a full page load.
         */
        if ($avatarWasChanged || Arr::hasAny($changes, ['timezone_id', 'settings'])) {
            BreakUserUiProps::run($user);
        }

        if ($avatarWasChanged) {
            Session::put('reloadLayout', '1');
        }

        /*
         * The organisation colours travel in the first load only layout props, so without asking for
         * those props again the left navigation would keep the old colours until a full page load.
         */
        if ($organisationColoursWereSubmitted) {
            Session::put('reloadLayout', '1');
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
            'nickname'          => ['sometimes', 'nullable', 'string', 'min:2', 'max:24', 'regex:/^[\pL\pN ._-]+$/u', Rule::unique('users', 'nickname')->ignore(request()->user()->id)],
            'language_id'       => ['sometimes', 'required', 'exists:languages,id'],
            'app_theme'         => ['sometimes', 'required'],
            'chat_theme'        => ['sometimes', 'nullable', Rule::in(['light', 'sky', 'blush', 'sand', 'mint', 'dracula', 'nord', 'gruvbox', 'monokai', 'onedark', 'solarized'])],
            'org_themes'                          => ['sometimes', 'array'],
            'org_themes.enabled'                  => ['sometimes', 'boolean'],
            'org_themes.themes'                   => ['sometimes', 'array'],
            'org_themes.themes.*.organisation_id' => ['required', 'integer'],
            'org_themes.themes.*.colour'          => ['required', 'string', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'chat_signature'    => ['sometimes', 'nullable', 'string', 'max:2000'],
            'hide_logo'         => ['sometimes', 'boolean'],
            'alert_sounds'      => ['sometimes', 'array'],
            'alert_preview_seconds' => ['sometimes', 'integer', 'between:2,30'],
            'alert_sounds.*'    => [Rule::in(['chime', 'bells', 'dingdong', 'pop', 'marimba', 'submarine', 'voice', 'bird', 'boing', 'fart', 'silent'])],
            'notifications'     => ['sometimes', 'array'],
            'notifications.*'   => ['array'],
            'notifications.*.*' => [Rule::in(UserNotificationEnum::CHANNELS)],
            'slack_user_id'     => ['sometimes', 'nullable', 'string', 'regex:/^[UW][A-Z0-9]{6,}$/', Rule::unique('users', 'slack_user_id')->ignore(request()->user()->id)],
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
            'stale_orders_days' => ['sometimes', 'integer', 'min:1'],
            'stale_orders_filters'                => ['sometimes', 'array'],
            'stale_orders_filters.show_aspos'     => ['sometimes', 'boolean'],
            'stale_orders_filters.show_pos'       => ['sometimes', 'boolean'],
            'stale_orders_filters.agents'         => ['sometimes', 'array'],
            'stale_orders_filters.agents.*'       => ['string'],
            'ticket_comments_newest_first'        => ['sometimes', 'boolean'],
            'ticket_history_newest_first'         => ['sometimes', 'boolean'],
            'tickets_list_mine'                   => ['sometimes', 'nullable', 'string', 'max:100'],
        ];
    }


    /**
     * Drops organisations the user can no longer reach, so a colour left behind by a revoked
     * access cannot travel with the settings.
     *
     * @param  array<int, array{organisation_id: int, colour: string}>  $themes
     * @return array<int, array{organisation_id: int, colour: string}>
     */
    protected function sanitiseOrganisationThemes(User $user, array $themes): array
    {
        $authorisedOrganisationIds = $user->authorisedOrganisations()->pluck('organisations.id')->all();

        $sanitised = [];
        foreach ($themes as $organisationTheme) {
            $organisationId = (int) Arr::get($organisationTheme, 'organisation_id');
            $colour         = Arr::get($organisationTheme, 'colour');

            if (!in_array($organisationId, $authorisedOrganisationIds) || !is_string($colour) || !preg_match('/^#[0-9A-Fa-f]{6}$/', $colour)) {
                continue;
            }

            $sanitised[$organisationId] = [
                'organisation_id' => $organisationId,
                'colour'          => strtolower($colour),
            ];
        }

        return array_values($sanitised);
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
