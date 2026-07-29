<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 05 May 2025 13:32:34 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Http\Middleware;

use App\Actions\Helpers\Images\GetPictureSources;
use App\Actions\Helpers\Language\UI\GetLanguagesOptions;
use App\Actions\Web\WebBlock\Concerns\WithIrisImageVariants;
use App\Http\Resources\Helpers\LanguageResource;
use App\Http\Resources\Web\WebsiteIrisResource;
use App\Models\Helpers\Language;
use App\Models\Web\Website;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;
use Throwable;

trait WithIrisInertia
{
    use WithIrisImageVariants;

    public const int HEADER_LOGO_SIZE = 160;

    /**
     * Header logos are stored as unresized originals in published_layout;
     * rebuild the source capped at HEADER_LOGO_SIZE (2x variant added by GetPictureSources).
     */
    protected function resizeHeaderLogo(array $header): array
    {
        $source = Arr::get($header, 'header.data.fieldValue.logo.image.source');
        if (!is_array($source)) {
            return $header;
        }

        $media = $this->findMediaFromImgProxyUrl(Arr::get($source, 'original'));
        if ($media) {
            data_set(
                $header,
                'header.data.fieldValue.logo.image.source',
                GetPictureSources::run($media->getImage()->resize(self::HEADER_LOGO_SIZE, self::HEADER_LOGO_SIZE))
            );
        }

        return $header;
    }
    public function getIrisData(Website $website): array
    {
        $locale = app()->getLocale();

        $cacheKey = "irisData:website:$website->id:locale:".$locale;
        $ttl      = config('iris.cache.iris_website_data_ttl');

        $compute = function () use ($website, $locale) {
            $shop = $website->shop;

            $headerLayout   = Arr::get($website->published_layout, 'header');
            $isHeaderActive = Arr::get($headerLayout, 'status');
            $menuLayout     = Arr::get($website->published_layout, 'menu');
            $isMenuActive   = Arr::get($menuLayout, 'status');

            $migrationRedirect = null;
            if ($website->is_migrating) {
                $migrationRedirect = [
                    'need_changes_url' => [
                        'https://'.$website->domain,
                        'http://'.$website->domain,
                        'https://www.'.$website->domain.'/',
                    ],
                    'to_url'           => 'https://v2.'.$website->domain
                ];
            }


            $currentLanguage = Language::where('code', $locale)->first();

            return [
                'header'               => $isHeaderActive == 'active'
                    ? $this->resizeHeaderLogo(Arr::get($website->published_layout, 'header', []))
                    : [],
                'menu'                 => $isMenuActive == 'active' ? Arr::get($website->published_layout, 'menu') : [],
                'shop'                 => [
                    'type'                  => $shop->type->value,
                    'id'                    => $shop->id,
                    'slug'                  => $shop->slug,
                    'name'                  => $shop->name,
                    'number_brands'         => $shop->stats->number_brands,
                    'number_current_brands' => $shop->stats->number_current_brands,
                    'number_tags'           => $shop->stats->number_brands,
                    'number_current_tags'   => $shop->stats->number_current_tags,
                    'location'              => is_string($shop->location) ? json_decode($shop->location, true) : $shop->location,
                ],
                "website"              => WebsiteIrisResource::make($website)->getArray(),
                'theme'                => Arr::get($website->published_layout, 'theme'),
                'luigisbox_tracker_id' => Arr::get($website->settings, 'luigisbox.tracker_id'),
                'is_have_gtm'          => (bool)Arr::get($website->settings, 'google_tag_id'),
                'currency'             => [
                    'code'   => $shop->currency->code,
                    'symbol' => $shop->currency->symbol,
                    'name'   => $shop->currency->name,
                ],
                'show_price' => (bool) Arr::get($website->settings, 'webpage.show_price', false),
                'locale'               => $locale,
                'website_i18n'         => [
                    'current_language' => LanguageResource::make($currentLanguage)->getArray(),
                    'shop_language'    => LanguageResource::make($shop->language)->getArray(),
                    'language_options' => GetLanguagesOptions::make()->getExtraShopLanguages($shop->extra_languages),
                ],
                'migration_redirect'   => $migrationRedirect
            ];
        };

        try {
            $irisData = Cache::remember($cacheKey, $ttl, $compute);
        } catch (Throwable) {
            $irisData = $compute();
        }

        return $irisData;
    }

}
