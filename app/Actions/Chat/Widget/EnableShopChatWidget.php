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

    /**
     * The tag a storefront loads the widget with. The key travels in the query string rather than
     * as an attribute so that the whole embed is one URL, which survives being retyped.
     *
     * It appends to the head rather than the body: a tag manager firing on initialisation runs
     * while the document is still being parsed, and document.body is null that early.
     */
    public function embedSnippet(string $key): string
    {
        $src = url('/chat-widget/v1.js').'?k='.$key;

        return <<<HTML
        <script>
          (function () {
            var s = document.createElement('script');
            s.src = '$src';
            s.async = true;
            (document.head || document.documentElement).appendChild(s);
          })();
        </script>
        HTML;
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
        $command->newLine();
        $command->line('Widget key: '.$result['key']);
        $command->newLine();
        $command->line('Paste this on the storefront, in Google Tag Manager as a Custom HTML tag');
        $command->line('firing on All Pages, or straight into the theme before </body>:');
        $command->newLine();
        $command->line($this->embedSnippet($result['key']));
        $command->newLine();
        $command->comment('The host comes from APP_URL. When testing through a tunnel, swap it for the tunnel host.');

        return 0;
    }
}
