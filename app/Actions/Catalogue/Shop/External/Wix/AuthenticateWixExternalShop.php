<?php

namespace App\Actions\Catalogue\Shop\External\Wix;

use App\Actions\Catalogue\Shop\Traits\WithWixExternalShopApi;
use App\Enums\Catalogue\Shop\ShopEngineEnum;
use App\Enums\Catalogue\Shop\ShopTypeEnum;
use App\Models\Catalogue\Shop;
use App\Models\Dropshipping\WixUser;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Support\Facades\URL;
use Illuminate\Validation\ValidationException;
use Lorisleiva\Actions\Concerns\AsAction;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

class AuthenticateWixExternalShop
{
    use AsAction;
    use WithWixExternalShopApi;

    public const array WIX_APPENDED_QUERY = ['appId', 'tenantId', 'instanceId', 'signedInstance'];

    public function handle(Shop $shop, string $signedInstance): RedirectResponse|string
    {
        try {
            $wixUser = DB::transaction(function () use ($shop, $signedInstance) {
                $instanceId = Arr::get($this->verifyWixSignedInstance($signedInstance), 'instanceId');

                if (!$instanceId) {
                    throw ValidationException::withMessages([
                        'message' => __('Wix sent an instance we could not verify')
                    ]);
                }

                $wixUser = WixUser::where('wix_instance_id', $instanceId)->first();

                if ($wixUser?->customer_id) {
                    throw ValidationException::withMessages([
                        'message' => __('This Wix site is already connected as a dropshipping channel')
                    ]);
                }

                if ($wixUser?->external_shop_id && $wixUser->external_shop_id !== $shop->id) {
                    throw ValidationException::withMessages([
                        'message' => __('This Wix site is already connected to another shop')
                    ]);
                }

                $tokenData   = $this->createWixAccessToken($instanceId);
                $accessToken = Arr::get($tokenData, 'access_token');

                if (!$accessToken) {
                    throw ValidationException::withMessages([
                        'message' => Arr::get($tokenData, 'message', __('Wix did not issue an access token'))
                    ]);
                }

                WixUser::where('external_shop_id', $shop->id)
                    ->where('wix_instance_id', '!=', $instanceId)
                    ->update(['external_shop_id' => null]);

                $wixUser ??= new WixUser([
                    'wix_instance_id' => $instanceId,
                    'name'            => $shop->name,
                ]);

                $wixUser->forceFill([
                    'group_id'               => $shop->group_id,
                    'organisation_id'        => $shop->organisation_id,
                    'external_shop_id'       => $shop->id,
                    'status'                 => true,
                    'access_token'           => $accessToken,
                    'access_token_expire_in' => $this->getWixAccessTokenExpiry($tokenData),
                ])->save();

                return $this->storeWixSiteData($wixUser);
            });

            GetWixStore::run($shop, $wixUser);
            GetWixProducts::dispatch($shop);

            return Redirect::route('grp.org.shops.show.catalogue.dashboard', [
                $shop->organisation->slug,
                $shop->slug
            ]);
        } catch (\Exception $e) {
            \Sentry::captureException($e);

            return $e->getMessage();
        }
    }

    public function storeWixSiteData(WixUser $wixUser): WixUser
    {
        $instance = $this->getWixAppInstance($wixUser);

        if (Arr::get($instance, 'message')) {
            return $wixUser;
        }

        $data = $wixUser->data ?? [];
        data_set($data, 'instance_id', Arr::get($instance, 'instance.instanceId'));
        data_set($data, 'app_name', Arr::get($instance, 'instance.appName'));
        data_set($data, 'site_id', Arr::get($instance, 'site.siteId'));
        data_set($data, 'site_display_name', Arr::get($instance, 'site.siteDisplayName'));
        data_set($data, 'locale', Arr::get($instance, 'site.locale'));
        data_set($data, 'currency', Arr::get($instance, 'site.paymentCurrency'));
        data_set($data, 'url', Arr::get($instance, 'site.url'));
        data_set($data, 'owner_email', Arr::get($instance, 'site.ownerInfo.email'));

        $wixUser->forceFill(array_filter([
            'data'        => $data,
            'name'        => Arr::get($instance, 'site.siteDisplayName'),
            'email'       => Arr::get($instance, 'site.ownerInfo.email'),
            'wix_site_id' => Arr::get($instance, 'site.siteId'),
            'site_url'    => Arr::get($instance, 'site.url'),
        ]))->save();

        return $wixUser;
    }

    public function getInstallUrlForShop(Shop $shop): string
    {
        $callback = URL::temporarySignedRoute('wix.link_external_shop', now()->addHours(2), [
            'shop' => $shop->id,
        ]);

        return $this->getWixInstallUrl($callback);
    }

    public function asController(Shop $shop, Request $request): RedirectResponse|string
    {
        if (!$request->hasValidSignatureWhileIgnoring(self::WIX_APPENDED_QUERY)) {
            throw new AccessDeniedHttpException('Invalid Wix install callback');
        }

        if ($shop->type !== ShopTypeEnum::EXTERNAL || $shop->engine !== ShopEngineEnum::WIX) {
            throw new AccessDeniedHttpException('Shop is not a Wix external shop');
        }

        if (!$request->query('instanceId') || !$request->query('signedInstance')) {
            return __('The Wix installation was not completed');
        }

        return $this->handle($shop, (string) $request->query('signedInstance'));
    }
}
