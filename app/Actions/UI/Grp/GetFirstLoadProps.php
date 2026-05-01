<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 18 Feb 2024 07:08:53 Central Standard Time, Mexico City, Mexico
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\UI\Grp;

use App\Actions\Dispatching\WaitingItems\GetCrmWaitingBadgeData;
use App\Actions\Dispatching\WaitingItems\GetDispatchingWaitingBadgeData;
use App\Actions\Helpers\Language\UI\GetLanguagesOptions;
use App\Actions\UI\Grp\Layout\GetLayout;
use App\Http\Resources\SysAdmin\NotificationsResource;
use App\Models\Helpers\Language;
use App\Models\SysAdmin\User;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Cache;
use Lorisleiva\Actions\Concerns\AsObject;
use Sentry;
use Tighten\Ziggy\Ziggy;
use Throwable;

class GetFirstLoadProps
{
    use AsObject;

    public function handle(?User $user): array
    {
        if ($user) {
            $language = $user->language;
        } else {
            $language = Language::where('code', App::currentLocale())->first();
        }
        if (!$language) {
            $language = Language::where('code', 'en')->first();
        }


        $cacheKey          = 'grp-first-load-props:'.($user?->id ?? 'guest').':'.$language->code;
        $ttl               = now()->addDays(7);
        $compute           = fn() => $this->getProps($user, $language);
        $shouldCacheLayout = (bool)config('ui.cache.layout');

        try {
            $props = $shouldCacheLayout
                ? Cache::remember($cacheKey, $ttl, $compute)
                : $compute();
        } catch (Throwable $e) {
            Sentry::captureException($e);
            $props = $compute();
        }


        data_set($props, 'notifications', $user ? NotificationsResource::collection($user->notifications()->orderBy('created_at', 'desc')->limit(10)->get())->collection : null);
        data_set($props, 'dispatching_waiting_count', $user ? GetDispatchingWaitingBadgeData::make()->totalCount($user) : 0);
        data_set($props, 'crm_waiting_count', $user ? GetCrmWaitingBadgeData::make()->totalCount($user) : 0);
        data_set($props, 'ziggy', new Ziggy('grp')->toArray());


        return $props;
    }

    private function getProps(?User $user, Language $language): array
    {
        $availableLanguages = Language::where('status', true)->pluck('id')->toArray();

        $image = null;
        if ($user && !blank($user->image_id)) {
            $image = $user->imageSources(0, 48);
        }

        return [
            'localeData' => [
                'locale_iso'            => getIsoLocale(App::getLocale()),
                'language'              => [
                    'id'          => $language->id,
                    'code'        => $language->code,
                    'name'        => $language->name,
                    'native_name' => $language->native_name,
                    'flag'        => $language->flag

                ],
                'languageOptions'       => GetLanguagesOptions::make()->getExtraGroupLanguages($availableLanguages),
                'languageAssetsOptions' => GetLanguagesOptions::make()->translated(),
            ],

            'layout'           => GetLayout::run($user),
            'environment'      => app()->environment(),
            'help_portal_url'  => config('app.help_portal_url'),
            'avatar_thumbnail' => $image,
        ];
    }

}
