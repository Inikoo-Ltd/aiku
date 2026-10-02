<?php

/*
 * Author: Artha <artha@aw-advantage.com>
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Chat\Widget;

use App\Models\Catalogue\Shop;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Lorisleiva\Actions\Concerns\AsObject;

class ChatWidgetKey
{
    use AsObject;

    /**
     * The key a storefront script identifies its shop by.
     *
     * It is not a secret: the public chat endpoints already take a shop id from the browser, so
     * anyone reading the page source learns nothing new. It exists so that the embed on a
     * storefront can be revoked by rotating it, without touching the shop itself.
     */
    public function ensure(Shop $shop): string
    {
        $key = Arr::get($shop->settings, 'chat.widget_key');

        if (is_string($key) && $key !== '') {
            return $key;
        }

        $key      = Str::lower(Str::random(32));
        $settings = $shop->settings ?? [];

        data_set($settings, 'chat.widget_key', $key);
        $shop->update(['settings' => $settings]);

        return $key;
    }

    public function resolveShop(?string $key): ?Shop
    {
        if (!is_string($key) || $key === '') {
            return null;
        }

        return Shop::whereRaw("settings #>> '{chat,widget_key}' = ?", [$key])->first();
    }
}
