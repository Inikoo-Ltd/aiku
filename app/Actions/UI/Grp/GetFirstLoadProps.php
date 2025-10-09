<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 18 Feb 2024 07:08:53 Central Standard Time, Mexico City, Mexico
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\UI\Grp;

use App\Actions\Helpers\Language\UI\GetLanguagesOptions;
use App\Actions\UI\Grp\Layout\GetLayout;
use App\Http\Resources\Helpers\LanguageResource;
use App\Http\Resources\SysAdmin\NotificationsResource;
use App\Models\Helpers\Language;
use App\Models\SysAdmin\User;
use Illuminate\Support\Facades\App;
use Lorisleiva\Actions\Concerns\AsObject;

class GetFirstLoadProps
{
    use AsObject;

    public function handle(?User $user): array
    {
        $availableLanguages = Language::where('status', true)->pluck('id')->toArray();

        if ($user) {
            $language = $user->language;
        } else {
            $language = Language::where('code', App::currentLocale())->first();
        }
        if (!$language) {
            $language = Language::where('code', 'en')->first();
        }

        return
            [
                'localeData' =>
                    [
                        'language'              => LanguageResource::make($language)->getArray(),
                        'languageOptions'       => GetLanguagesOptions::make()->getExtraGroupLanguages($availableLanguages),
                        'languageAssetsOptions' => GetLanguagesOptions::make()->translated(),
                    ],

                'layout'           => GetLayout::run($user),
                'environment'      => app()->environment(),
                'help_portal_url'  => config('app.help_portal_url'),
                'avatar_thumbnail' => !blank($user->image_id) ? $user->imageSources(0, 48) : null,
                'notifications'    => NotificationsResource::collection($user->notifications()->orderBy('created_at', 'desc')->limit(10)->get())->collection,


            ];
    }
}
