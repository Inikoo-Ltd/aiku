<?php

/*
 * Author: Artha <artha@aw-advantage.com>
 * Created: Wed, 02 Jul 2025 13:35:13 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Retina\Dropshipping\CustomerSalesChannel;

use App\Actions\Dropshipping\Ebay\CheckEbayChannel;
use App\Actions\Dropshipping\Ebay\ReAuthorizeRetinaEbayUser;
use App\Actions\Dropshipping\Magento\ReAuthorizeMagentoUser;
use App\Actions\Dropshipping\WooCommerce\ReAuthorizeRetinaWooCommerceUser;
use App\Actions\RetinaAction;
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
    use WithActionUpdate;

    public function handle(CustomerSalesChannel $customerSalesChannel, ?ActionRequest $request): ?string
    {
        /** @var MagentoUser|ShopifyUser|WooCommerceUser|EbayUser $platformUser */
        $platformUser = $customerSalesChannel->user;

        return match ($customerSalesChannel->platform->type) {
            PlatformTypeEnum::SHOPIFY => route('pupil.authenticate', [
                'shop' => $platformUser->name
            ]),
            PlatformTypeEnum::WOOCOMMERCE => ReAuthorizeRetinaWooCommerceUser::run($platformUser),
            PlatformTypeEnum::MAGENTO => ReAuthorizeMagentoUser::run($platformUser),
            PlatformTypeEnum::EBAY => ReAuthorizeRetinaEbayUser::make()->action($platformUser, $request),
            default => null
        };
    }

    public function asController(CustomerSalesChannel $customerSalesChannel, ActionRequest $request): string
    {
        $this->initialisation($request);

        return $this->handle($customerSalesChannel, $request);
    }
}
