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
use App\Models\Helpers\Language;
use App\Models\SysAdmin\User;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Cache;
use Lorisleiva\Actions\Concerns\AsObject;

class GetFirstLoadProps
{
    use AsObject;

    public function handle(?User $user): array
    {
        if (!$user) {
            $language = Language::where('code', App::currentLocale())->first();

            return [

                'localeData' =>
                    [
                        'language'        => LanguageResource::make($language)->getArray(),
                    ],

                'layout'      => [],
                'environment' => app()->environment(),
            ];
        }

        $key= 'grp.first_load_props.'.$user->id;

        $props= Cache::remember(
            $key,
            604800,
            function () use ($user) {
                return $this->getFirstLoadProps($user);
            }
        );


        $props['environment'] = app()->environment();

        return $props;
    }

    public function getFirstLoadProps(User $user): array
    {
        $language = $user->language;

        if (!$language) {
            $language = Language::where('code', 'en')->first();
        }
        return  [
            'localeData' =>
                [
                    'language'        => LanguageResource::make($language)->getArray(),
                    'languageOptions' => GetLanguagesOptions::make()->translated(),
                ],

            'layout'      => GetLayout::run($user),

        ];

    }


}
