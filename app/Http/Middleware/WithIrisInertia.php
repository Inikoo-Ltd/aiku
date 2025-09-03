<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 05 May 2025 13:32:34 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Http\Middleware;

use App\Actions\Catalogue\ProductCategory\Json\GetIrisProductCategoryNavigation;
use App\Actions\Helpers\Language\UI\GetLanguagesOptions;
use App\Enums\Catalogue\Shop\ShopTypeEnum;
use App\Http\Resources\Helpers\LanguageResource;
use App\Http\Resources\UI\LoggedWebUserResource;
use App\Http\Resources\Web\WebsiteIrisResource;
use App\Models\CRM\WebUser;
use App\Models\Helpers\Language;
use App\Models\Ordering\Order;
use App\Models\Web\Website;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

trait WithIrisInertia
{
    public function getIrisData(Website $website, ?WebUser $webUser): array
    {
        $shop = $website->shop;

        $headerLayout   = Arr::get($website->published_layout, 'header');
        $isHeaderActive = Arr::get($headerLayout, 'status');
        $footerLayout   = Arr::get($website->published_layout, 'footer');
        $isFooterActive = Arr::get($footerLayout, 'status');
        $menuLayout     = Arr::get($website->published_layout, 'menu');
        $isMenuActive   = Arr::get($menuLayout, 'status');


        $cartCount  = 0;
        $cartAmount = 0;
        $itemsCount = 0;
        if ($webUser && $shop->type == ShopTypeEnum::B2B) {
            $orderInBasket = $webUser->customer->orderInBasket;
            $cartCount     = $orderInBasket ? $orderInBasket->stats->number_item_transactions : 0;
            $cartAmount    = $orderInBasket ? $orderInBasket->total_amount : 0;
            $itemsCount    = $orderInBasket ? intval($this->countItems($orderInBasket)) : 0;
        }

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
            'shop'                 => [
                'type' => $shop->type->value,
                'id'   => $shop->id,
                'slug' => $shop->slug,
                'name' => $shop->name,
            ],
            "website"              => WebsiteIrisResource::make($website)->getArray(),
            'theme'                => Arr::get($website->published_layout, 'theme'),
            'luigisbox_tracker_id' => Arr::get($website->settings, 'luigisbox.tracker_id'),
            'is_logged_in'         => (bool)$webUser,
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
            'user_auth'          => $webUser ? LoggedWebUserResource::make($webUser)->getArray() : null,
            'customer'           => $webUser?->customer,
            'variables'          => [
                'reference'        => $webUser?->customer?->reference,
                'name'             => $webUser?->contact_name,
                'username'         => $webUser?->username,
                'email'            => $webUser?->email,
                'favourites_count' => $webUser?->customer?->stats?->number_favourites,
                'items_count'      => $itemsCount,  // Count of all items with the quantity
                'cart_count'       => $cartCount,  // Count of unique items
                'cart_amount'      => $cartAmount,
            ],
            'migration_redirect' => $migrationRedirect
        ];
    }

    public function countItems(Order $order)
    {
        return DB::table('transactions')
            ->where('order_id', $order->id)
            ->where('model_type', 'Product')
            ->whereNull('deleted_at')
            ->sum('quantity_ordered');
    }
}
