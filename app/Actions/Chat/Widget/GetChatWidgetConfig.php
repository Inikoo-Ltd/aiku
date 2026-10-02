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
            'shop_id'      => $shop->id,
            'name'         => $shop->name,
            'theme'        => Arr::get($shop->settings, 'chat.widget_theme'),
            'broadcasting' => $this->broadcasting(),
        ];
    }

    /**
     * What the storefront needs to listen for replies as they are written.
     *
     * A conversation is a public channel, so this is the same key the page would get from any of
     * our own apps and carries no secret. It comes from the server rather than from the bundle
     * because one built widget is served by whichever environment is holding it.
     *
     * @return array{key: string|null, cluster: string|null, host: string, port: int, scheme: string}|null
     */
    private function broadcasting(): ?array
    {
        $key = config('broadcasting.connections.pusher.key');

        if (blank($key)) {
            return null;
        }

        $options = config('broadcasting.connections.pusher.options', []);
        $cluster = Arr::get($options, 'cluster');

        return [
            'key'     => $key,
            'cluster' => $cluster,
            'host'    => env('PUSHER_HOST') ?: 'ws-'.($cluster ?: 'mt1').'.pusher.com',
            'port'    => (int) env('PUSHER_PORT', 443),
            'scheme'  => (string) env('PUSHER_SCHEME', 'https'),
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
