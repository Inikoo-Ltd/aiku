<?php

namespace App\Actions\Catalogue\Shop\External\Shopify;

use App\Actions\Catalogue\Shop\Traits\WithShopifyExternalShopApi;
use App\Actions\OrgAction;
use App\Enums\Catalogue\Shop\ShopEngineEnum;
use App\Enums\Catalogue\Shop\ShopTypeEnum;
use App\Enums\Ordering\Platform\PlatformTypeEnum;
use App\Models\Catalogue\Shop;
use App\Models\Dropshipping\Platform;
use App\Models\Dropshipping\ShopifyUser;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

class ConnectShopifyExternalShop extends OrgAction
{
    use WithShopifyExternalShopApi;

    public string $commandSignature = 'external_shop:shopify_connect {shop} {store}';

    /**
     * Links a Shopify store to an existing external shop; the store finishes the link by installing the app
     * from the returned authentication url.
     *
     * @throws \Throwable
     */
    public function handle(Shop $shop, string $store): ShopifyUser
    {
        if ($shop->type !== ShopTypeEnum::EXTERNAL || $shop->engine !== ShopEngineEnum::SHOPIFY) {
            throw ValidationException::withMessages(['shop' => __('Shop is not a Shopify external shop')]);
        }

        $domain = $this->resolveShopifyStoreDomain($store);

        if (!$domain) {
            throw ValidationException::withMessages(['store' => __('Shopify store :store not found', ['store' => $store])]);
        }

        return DB::transaction(function () use ($shop, $domain) {
            $shopifyUser = ShopifyUser::where('name', $domain)->first();

            if ($shopifyUser?->customer_id) {
                throw ValidationException::withMessages([
                    'store' => __('This Shopify store is a dropshipping channel, close that channel before connecting it to this shop')
                ]);
            }

            if ($shopifyUser?->external_shop_id && $shopifyUser->external_shop_id !== $shop->id) {
                throw ValidationException::withMessages(['store' => __('This Shopify store is already connected to another shop')]);
            }

            ShopifyUser::where('external_shop_id', $shop->id)
                ->where('name', '!=', $domain)
                ->update(['external_shop_id' => null]);

            $shopifyUser ??= new ShopifyUser([
                'name'     => $domain,
                'username' => Str::random(4),
                'password' => Str::random(8),
            ]);

            $shopifyUser->forceFill([
                'group_id'         => $shop->group_id,
                'organisation_id'  => $shop->organisation_id,
                'platform_id'      => Platform::where('type', PlatformTypeEnum::SHOPIFY->value)->value('id'),
                'external_shop_id' => $shop->id,
            ])->save();

            $settings = $shop->settings ?? [];
            data_set($settings, 'shopify.shop_url', $domain);
            data_set($settings, 'shopify.auth_url', $this->getAuthUrl($shopifyUser));
            $shop->update(['settings' => $settings]);

            return $shopifyUser;
        });
    }

    public function getAuthUrl(ShopifyUser $shopifyUser): string
    {
        return route('pupil.authenticate', ['shop' => $shopifyUser->name]);
    }

    /**
     * Shopify gives every store a permanent handle (abc123-xy.myshopify.com) and the name the merchant chose is
     * only an alias, while the app install comes back under the permanent one, so the store is kept under it.
     */
    public function resolveShopifyStoreDomain(string $store): ?string
    {
        $handle = $this->getShopifyStoreHandle($store);

        if (!$handle) {
            return null;
        }

        $myShopifyDomain = config('shopify-app.my_shopify_domain');

        try {
            $permanentDomain = Http::timeout(10)
                ->withOptions(['allow_redirects' => false])
                ->get('https://'.$handle.'.'.$myShopifyDomain.'/meta.json')
                ->json('myshopify_domain');
        } catch (Throwable) {
            return null;
        }

        if (!is_string($permanentDomain) || !preg_match('/^[a-z0-9-]+\.myshopify\.com$/', $permanentDomain)) {
            return null;
        }

        return $permanentDomain;
    }

    public function getShopifyStoreHandle(string $store): ?string
    {
        $handle = trim($store);
        $handle = preg_replace('#^https?:?/+#i', '', $handle);

        if (preg_match('#^admin\.shopify\.com/store/([^/?\#]+)#i', $handle, $matches)) {
            $handle = $matches[1];
        }

        $handle = preg_replace('#/.*$#', '', $handle);
        $handle = Str::lower(trim(preg_replace('/\.myshopify\.com$/i', '', $handle)));

        return preg_match('/^[a-z0-9-]+$/', $handle) ? $handle : null;
    }

    public function asCommand(Command $command): int
    {
        $shop = Shop::where('slug', $command->argument('shop'))->firstOrFail();

        try {
            $shopifyUser = $this->handle($shop, (string) $command->argument('store'));
        } catch (ValidationException $e) {
            $command->error(collect($e->errors())->flatten()->join(' '));

            return 1;
        }

        $command->info("Shopify store $shopifyUser->name linked to $shop->name.");
        $command->line('Open this link, logged in as the store owner, to install the app and finish the connection:');
        $command->line($this->getAuthUrl($shopifyUser));

        return 0;
    }
}
