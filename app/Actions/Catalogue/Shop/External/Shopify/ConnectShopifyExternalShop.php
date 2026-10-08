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
use App\Models\SysAdmin\Organisation;
use Illuminate\Console\Command;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Lorisleiva\Actions\ActionRequest;
use Throwable;

class ConnectShopifyExternalShop extends OrgAction
{
    use WithShopifyExternalShopApi;

    public string $commandSignature = 'external_shop:shopify_connect {shop} {store}';

    /**
     * Links a Shopify store to an existing external shop. A store with no token yet finishes the link by installing
     * the app from the returned authentication url.
     *
     * @throws \Throwable
     */
    public function handle(Shop $shop, string $store): ShopifyUser
    {
        if ($shop->type !== ShopTypeEnum::EXTERNAL || $shop->engine !== ShopEngineEnum::SHOPIFY) {
            throw ValidationException::withMessages(['shopify_store' => __('Shop is not a Shopify external shop')]);
        }

        $domain = $this->resolveShopifyStoreDomain($store);

        if (!$domain) {
            throw ValidationException::withMessages(['shopify_store' => __('Shopify store :store not found', ['store' => $store])]);
        }

        return DB::transaction(function () use ($shop, $domain) {
            $shopifyUser = ShopifyUser::where('name', $domain)->first();

            if ($shopifyUser?->external_shop_id && $shopifyUser->external_shop_id !== $shop->id) {
                throw ValidationException::withMessages(['shopify_store' => __('This Shopify store is already connected to another shop')]);
            }

            ShopifyUser::where('external_shop_id', $shop->id)
                ->where('name', '!=', $domain)
                ->update(['external_shop_id' => null]);

            if ($shopifyUser) {
                $shopifyUser->forceFill(['external_shop_id' => $shop->id])->save();
            } else {
                $shopifyUser = ShopifyUser::create([
                    'name'             => $domain,
                    'username'         => Str::random(4),
                    'password'         => Str::random(8),
                    'group_id'         => $shop->group_id,
                    'organisation_id'  => $shop->organisation_id,
                    'platform_id'      => Platform::where('type', PlatformTypeEnum::SHOPIFY->value)->value('id'),
                    'external_shop_id' => $shop->id,
                ]);
            }

            $settings = $shop->settings ?? [];
            data_set($settings, 'shopify.shop_url', $domain);

            if ($this->isShopifyStoreInstalled($shopifyUser)) {
                data_forget($settings, 'shopify.auth_url');
            } else {
                data_set($settings, 'shopify.auth_url', $this->getAuthUrl($shopifyUser));
            }

            $shop->update(['settings' => $settings]);

            return $shopifyUser;
        });
    }

    /**
     * A store already serving a dropshipping channel, or linked before, holds a token: it is linked as it is and
     * needs no new app install.
     */
    public function isShopifyStoreInstalled(ShopifyUser $shopifyUser): bool
    {
        return !$shopifyUser->trashed() && str_starts_with((string) $shopifyUser->password, self::SHOPIFY_ACCESS_TOKEN_PREFIX);
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

    public function authorize(ActionRequest $request): bool
    {
        if ($this->asAction) {
            return true;
        }

        return $request->user()->authTo(['org-admin.'.$this->organisation->id, 'shop-admin.'.$this->shop->id]);
    }

    public function rules(): array
    {
        return [
            'shopify_store' => ['required', 'string', 'max:255'],
        ];
    }

    /**
     * @throws \Throwable
     */
    public function asController(Organisation $organisation, Shop $shop, ActionRequest $request): ShopifyUser
    {
        $this->initialisationFromShop($shop, $request);

        return $this->handle($shop, $this->validatedData['shopify_store']);
    }

    public function htmlResponse(ShopifyUser $shopifyUser): RedirectResponse
    {
        if ($this->isShopifyStoreInstalled($shopifyUser)) {
            return Redirect::back();
        }

        return Redirect::back()->with('redirect', [
            'url'    => $this->getAuthUrl($shopifyUser),
            'target' => '_blank',
        ]);
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

        if (!$this->isShopifyStoreInstalled($shopifyUser)) {
            $command->line('Open this link, logged in as the store owner, to install the app and finish the connection:');
            $command->line($this->getAuthUrl($shopifyUser));
        }

        return 0;
    }
}
