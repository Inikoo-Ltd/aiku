<?php

/*
 * Author: Artha <artha@aw-advantage.com>
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Chat\Widget;

use App\Actions\Catalogue\Shop\Seeders\SeedShopPermissions;
use App\Models\Catalogue\Shop;
use Illuminate\Console\Command;
use Lorisleiva\Actions\Concerns\AsAction;

class EnableShopChatWidget
{
    use AsAction;

    public string $commandSignature = 'chat:enable-widget {shop : Shop slug} {--disable}';

    public string $commandDescription = 'Turn the storefront chat widget on or off for a shop and seed its chat permissions';

    /**
     * @return array{key: string|null, enabled: bool}
     */
    public function handle(Shop $shop, bool $enabled = true): array
    {
        $settings = $shop->settings ?? [];
        data_set($settings, 'chat.enabled', $enabled);
        $shop->update(['settings' => $settings]);

        $shop->refresh();

        /*
         * The permissions are what an agent needs to see these conversations at all, and they are
         * created from the shop's own settings, so they are seeded here rather than left to a
         * later run of shop:seed-permissions over every shop.
         */
        SeedShopPermissions::run($shop);

        return [
            'key'     => $enabled ? ChatWidgetKey::make()->ensure($shop) : null,
            'enabled' => $enabled,
        ];
    }

    public function asCommand(Command $command): int
    {
        $shop = Shop::where('slug', $command->argument('shop'))->first();

        if (!$shop) {
            $command->error('No shop with slug '.$command->argument('shop'));

            return 1;
        }

        $enabled = !$command->option('disable');
        $result  = $this->handle($shop, $enabled);

        if (!$enabled) {
            $command->info('Chat disabled for '.$shop->name);

            return 0;
        }

        $command->info('Chat enabled for '.$shop->name);
        $command->line('Widget key: '.$result['key']);
        $command->line('Script:     '.route('grp.api.chats.widget.config').'  (embed uses key='.$result['key'].')');

        return 0;
    }
}
