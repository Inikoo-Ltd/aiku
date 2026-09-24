<?php

/*
 * Author: Artha <artha@aw-advantage.com>
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Chat\Widget;

use App\Enums\SysAdmin\Authorisation\ShopPermissionsEnum;
use App\Models\Catalogue\Shop;
use Illuminate\Support\Arr;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;

class GetChatWidgetConfig
{
    use AsAction;

    /**
     * What a storefront needs before it can draw the chat.
     *
     * This is read by a script running on somebody else's domain, so it answers to a widget key
     * rather than to a session, and it carries nothing the page is not already entitled to see.
     */
    public function handle(Shop $shop): array
    {
        return [
            'shop_id' => $shop->id,
            'name'    => $shop->name,
            'theme'   => Arr::get($shop->settings, 'chat.widget_theme'),
        ];
    }

    public function asController(ActionRequest $request): array
    {
        $key = $request->query('key');

        $shop = ChatWidgetKey::make()->resolveShop(is_string($key) ? $key : null);

        if (!$shop || !ShopPermissionsEnum::shopHasChat($shop)) {
            abort(404, 'Unknown chat widget');
        }

        return $this->handle($shop);
    }

    public function jsonResponse(array $config): array
    {
        return [
            'data' => $config,
        ];
    }
}
