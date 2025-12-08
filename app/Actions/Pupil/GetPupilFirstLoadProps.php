<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 15 Aug 2024 11:59:42 Central Indonesia Time, Bali Office, Indonesia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Pupil;

use App\Actions\Helpers\Language\UI\GetLanguagesOptions;
use App\Actions\Retina\UI\Layout\GetPupilDropshippingNavigation;
use App\Http\Resources\Helpers\LanguageResource;
use App\Models\Dropshipping\ShopifyUser;
use App\Models\Helpers\Language;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Lorisleiva\Actions\Concerns\AsObject;

class GetPupilFirstLoadProps
{
    use AsObject;

    public function handle(Request $request, ?ShopifyUser $shopifyUser): array
    {
        if ($shopifyUser) {
            $language = $shopifyUser->language;
        } else {
            $language = Language::where('code', App::currentLocale())->first();
        }
        if (! $language) {
            $language = Language::where('code', 'en')->first();
        }

        return
            [
                'localeData' => [
                    'language' => LanguageResource::make($language)->getArray(),
                    'languageOptions' => GetLanguagesOptions::make()->translated(),
                ],
                'layout' => [
                    'navigation' => GetPupilDropshippingNavigation::run($shopifyUser),
                ],
                'environment' => app()->environment(),
            ];
    }
}
