<?php

/*
 * Author: Artha <artha@aw-advantage.com>
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Pupil\Chat;

use App\Enums\Catalogue\Shop\ShopStateEnum;
use App\Enums\Catalogue\Shop\ShopTypeEnum;
use App\Enums\SysAdmin\Authorisation\ShopPermissionsEnum;
use App\Models\Catalogue\Shop;
use App\Models\Dropshipping\ShopifyUser;
use Lorisleiva\Actions\Concerns\AsObject;

class GetPupilChatShop
{
    use AsObject;

    /**
     * The shop whose agents answer a merchant writing from the Shopify app.
     *
     * Once the merchant has linked an account there is nothing to decide. Before that we know
     * only their Shopify store and its language, so the conversation goes to the shop speaking
     * that language, and to the oldest open one when none of them does.
     */
    public function handle(?ShopifyUser $shopifyUser): ?Shop
    {
        if (!$shopifyUser) {
            return null;
        }

        $linkedShop = $shopifyUser->customer?->shop;

        if ($linkedShop) {
            return ShopPermissionsEnum::shopHasChat($linkedShop) ? $linkedShop : null;
        }

        $candidates = Shop::where('type', ShopTypeEnum::DROPSHIPPING)
            ->where('state', ShopStateEnum::OPEN)
            ->orderBy('id')
            ->get()
            ->filter(fn (Shop $shop) => ShopPermissionsEnum::shopHasChat($shop));

        return $candidates->firstWhere('language_id', $shopifyUser->language_id)
            ?? $candidates->first();
    }
}
