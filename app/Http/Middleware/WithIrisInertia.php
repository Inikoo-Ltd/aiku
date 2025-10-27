<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 05 May 2025 13:32:34 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Http\Middleware;

use App\Actions\Catalogue\ProductCategory\Json\GetIrisProductCategoryNavigation;
use App\Actions\Helpers\Language\UI\GetLanguagesOptions;
use App\Http\Resources\Helpers\LanguageResource;
use App\Http\Resources\Web\WebsiteIrisResource;
use App\Models\Helpers\Language;
use App\Models\Web\Website;
use Illuminate\Support\Arr;

trait WithIrisInertia
{
    public function getIrisData(Website $website): array
    {
        $shop = $website->shop;

        $headerLayout   = Arr::get($website->published_layout, 'header');
        $isHeaderActive = Arr::get($headerLayout, 'status');
        $footerLayout   = Arr::get($website->published_layout, 'footer');
        $isFooterActive = Arr::get($footerLayout, 'status');
        $menuLayout     = Arr::get($website->published_layout, 'menu');
        $isMenuActive   = Arr::get($menuLayout, 'status');
        $sidebarLayout     = Arr::get($website->published_layout, 'menu');
        $isSidebarActive   = Arr::get($sidebarLayout, 'status');



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


        $currentLanguage = Language::where('code', app()->getLocale())->first();

        return [
            'header'               => array_merge(
                $isHeaderActive == 'active' ? Arr::get($website->published_layout, 'header') : [],
            ),
            'footer'               => array_merge(
                $isFooterActive == 'active' ? Arr::get($website->published_layout, 'footer') : [],
            ),
            'menu'                 => array_merge(
                $isMenuActive == 'active' ? Arr::get($website->published_layout, 'menu') : [],
                ['product_categories' => GetIrisProductCategoryNavigation::run($website)]
            ),
            'sidebar'                 => array_merge(
                $isSidebarActive == 'active' ? Arr::get($website->published_layout, 'sidebar', []) : [],
                ['product_categories' => GetIrisProductCategoryNavigation::run($website)]
            ),
            'shop'                 => [
                'type' => $shop->type->value,
                'id'   => $shop->id,
                'slug' => $shop->slug,
                'name' => $shop->name,
                'number_brands' => $shop->stats->number_brands,
                'number_current_brands' => $shop->stats->number_current_brands,
                'number_tags' => $shop->stats->number_brands,
                'number_current_tags' => $shop->stats->number_current_tags
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
            'locale'               => app()->getLocale(),
            'website_i18n'       => [
                'current_language' => LanguageResource::make($currentLanguage)->getArray(),
                'shop_language'    => LanguageResource::make($shop->language)->getArray(),
                'language_options' => GetLanguagesOptions::make()->getExtraShopLanguages($shop->extra_languages),
            ],
            'migration_redirect' => $migrationRedirect
        ];
    }

}
