<?php

/*
 * Author: Artha <artha@aw-advantage.com>
 * Created: Wed, 02 Jul 2025 13:35:13 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Retina\Dropshipping\CustomerSalesChannel;

use App\Actions\Dropshipping\Allegro\User\AuthenticateAllegroAccount;
use App\Actions\Dropshipping\Ebay\ReAuthorizeRetinaEbayUser;
use App\Actions\Dropshipping\Magento\ReAuthorizeMagentoUser;
use App\Actions\Dropshipping\ShopifyUser\ClaimShopifyUser;
use App\Actions\Dropshipping\Tiktok\User\AuthenticateTiktokAccount;
use App\Actions\Dropshipping\Wix\User\AuthenticateWixAccount;
use App\Actions\Dropshipping\WooCommerce\AuthorizeRetinaWooCommerceUser;
use App\Actions\Dropshipping\WooCommerce\ReAuthorizeRetinaWooCommerceUser;
use App\Actions\RetinaAction;
use App\Actions\Traits\WithRetinaCustomerOwnedRouteModels;
use App\Actions\Traits\WithActionUpdate;
use App\Models\Dropshipping\CustomerSalesChannel;
use App\Enums\Ordering\Platform\PlatformTypeEnum;
use App\Models\Dropshipping\EbayUser;
use App\Models\Dropshipping\MagentoUser;
use App\Models\Dropshipping\ShopifyUser;
use App\Models\Dropshipping\WooCommerceUser;
use Lorisleiva\Actions\ActionRequest;

class ReconnectRetinaCustomerSalesChannel extends RetinaAction
{
    use WithRetinaCustomerOwnedRouteModels;
    use WithActionUpdate;

    /**
     * A WooCommerce store that moved to a new domain is reconnected with the address it has now.
     */
    public function handle(CustomerSalesChannel $customerSalesChannel, ?ActionRequest $request, ?string $newStoreUrl = null): ?string
    {
        /** @var MagentoUser|ShopifyUser|WooCommerceUser|EbayUser $platformUser */
        $platformUser = $customerSalesChannel->user;

        if (! $platformUser) {
            return null;
        }

        return match ($customerSalesChannel->platform->type) {
            PlatformTypeEnum::SHOPIFY => ClaimShopifyUser::make()->authenticateUrl($customerSalesChannel->customer, $platformUser->name),
            PlatformTypeEnum::WOOCOMMERCE => ReAuthorizeRetinaWooCommerceUser::run($platformUser, 3600, $newStoreUrl),
            PlatformTypeEnum::MAGENTO => ReAuthorizeMagentoUser::run($platformUser),
            PlatformTypeEnum::EBAY => ReAuthorizeRetinaEbayUser::make()->action($platformUser, $request),
            PlatformTypeEnum::ALLEGRO => AuthenticateAllegroAccount::make()->redirectToAllegro($customerSalesChannel->customer),
            PlatformTypeEnum::WIX => AuthenticateWixAccount::make()->redirectToWix($customerSalesChannel->customer),
            PlatformTypeEnum::TIKTOK => AuthenticateTiktokAccount::make()->redirectToTikTok($customerSalesChannel->customer),
            default => null
        };
    }

    public function asController(CustomerSalesChannel $customerSalesChannel, ActionRequest $request): ?string
    {
        $this->initialisation($request);

        return $this->handle($customerSalesChannel, $request, $this->get('url'));
    }

    public function prepareForValidation(ActionRequest $request): void
    {
        if ($request->filled('url')) {
            $this->set('url', AuthorizeRetinaWooCommerceUser::storeHomeUrl($request->input('url')));
        }
    }

    public function rules(): array
    {
        return [
            'url' => [
                'bail',
                'sometimes',
                'nullable',
                'string',
                'url',
                'regex:/^https:\/\//',
                function ($attribute, $value, $fail) {
                    AuthorizeRetinaWooCommerceUser::make()->validateStoreIsReachable(rtrim($value, '/'), $fail);
                }
            ],
        ];
    }
}
