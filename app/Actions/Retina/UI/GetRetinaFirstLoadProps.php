<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 18 Feb 2024 07:11:43 Central Standard Time, Mexico City, Mexico
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Retina\UI;

use App\Actions\Helpers\Language\UI\GetLanguagesOptions;
use App\Actions\Retina\UI\Layout\GetRetinaLayout;
use App\Http\Resources\Helpers\LanguageResource;
use App\Models\Catalogue\Shop;
use App\Models\CRM\WebUser;
use App\Models\Helpers\Language;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\App;
use Lorisleiva\Actions\Concerns\AsObject;

class GetRetinaFirstLoadProps
{
    use AsObject;

    public function handle(Request $request, ?WebUser $webUser): array
    {
        /** @var Shop $shop */
        $shop = $request->get('website')->shop;

        if ($webUser) {
            $language = $webUser->language;
        } else {
            $language = Language::where('code', App::currentLocale())->first();
        }
        if (!$language) {
            $language = Language::where('code', Arr::first($shop->extra_languages ?? []))->first();
        }


        return
            [
                'localeData' =>
                [
                    'language'        => LanguageResource::make($language)->getArray(),
                    'languageOptions' => GetLanguagesOptions::make()->getExtraShopLanguages($shop->extra_languages),
                ],

                'layout'   => GetRetinaLayout::run($request, $webUser),
                'environment' => app()->environment(),
        ];
    }
}
