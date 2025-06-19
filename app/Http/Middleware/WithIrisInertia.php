<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 05 May 2025 13:32:34 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Http\Middleware;

use App\Enums\Catalogue\Shop\ShopTypeEnum;
use App\Http\Resources\UI\LoggedWebUserResource;
use App\Http\Resources\Web\WebsiteIrisResource;
use App\Models\CRM\WebUser;
use App\Models\Web\Website;
use Illuminate\Support\Arr;

trait WithIrisInertia
{
    public function getIrisData(Website $website, ?WebUser $webUser): array
    {
        $headerLayout   = Arr::get($website->published_layout, 'header');
        $isHeaderActive = Arr::get($headerLayout, 'status');
        $footerLayout   = Arr::get($website->published_layout, 'footer');
        $isFooterActive = Arr::get($footerLayout, 'status');
        $menuLayout     = Arr::get($website->published_layout, 'menu');
        $isMenuActive   = Arr::get($menuLayout, 'status');


        $cartCount  = 0;
        $cartAmount = 0;
        if ($webUser && $webUser->website->shop->type == ShopTypeEnum::B2B) {
            $orderInBasket = $webUser->customer->orderInBasket;
            $cartCount     = $orderInBasket ? $orderInBasket->stats->number_item_transactions : 0;
            $cartAmount    = $orderInBasket ? $orderInBasket->total_amount : 0;
        }
        return [
            'header'       => array_merge(
                $isHeaderActive == 'active' ? Arr::get($website->published_layout, 'header') : [],
            ),
            'footer'       => array_merge(
                $isFooterActive == 'active' ? Arr::get($website->published_layout, 'footer') : [],
            ),
            'menu'         => array_merge(
                $isMenuActive == 'active' ? Arr::get($website->published_layout, 'menu') : [],
            ),
            'shop'        => [
                'type' => $website->shop?->type?->value,
                'id'   => $website->shop?->id,
                'slug' => $website->shop?->slug,
                'name' => $website->shop?->name,
            ],
            "website"      => WebsiteIrisResource::make($website)->getArray(),
            'theme'        => Arr::get($website->published_layout, 'theme'),
            'luigisbox_tracker_id' => Arr::get($website->settings, 'luigisbox.tracker_id'),
            'is_logged_in' => (bool)$webUser,
            'currency' => [
                    'code'   => $website->shop->currency->code,
                    'symbol' => $website->shop->currency->symbol,
                    'name'   => $website->shop->currency->name,
                ],
            'user_auth'    => $webUser ? LoggedWebUserResource::make($webUser)->getArray() : null,
            'customer'     => $webUser?->customer,
            'variables'    => [
                'reference'        => $webUser?->customer?->reference,
                'name'             => $webUser?->contact_name,
                'username'         => $webUser?->username,
                'email'            => $webUser?->email,
                'favourites_count' => $webUser?->customer?->stats?->number_favourites,
                'cart_count'       => $cartCount,
                'cart_amount'      => $cartAmount,
            ]
        ];
    }
}
