<?php

/*
 * Author: Artha <artha@aw-advantage.com>
 * Created: Wed, 02 Jul 2025 13:35:13 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Retina\Dropshipping\CustomerSalesChannel;

use App\Actions\Dropshipping\Ebay\CheckEbayChannel;
use App\Actions\Dropshipping\Shopify\CheckShopifyChannel;
use App\Actions\Dropshipping\WooCommerce\CheckWooChannel;
use App\Actions\RetinaAction;
use App\Actions\Traits\WithActionUpdate;
use App\Models\Dropshipping\CustomerSalesChannel;
use App\Enums\Ordering\Platform\PlatformTypeEnum;
use Lorisleiva\Actions\ActionRequest;

class UnSuspendRetinaCustomerSalesChannel extends RetinaAction
{
    use WithActionUpdate;

    public function handle(CustomerSalesChannel $customerSalesChannel): ?string
    {
        $platformUser = $customerSalesChannel->user;

        if (! $platformUser) {
            return null;
        }

        /** @var CustomerSalesChannel|null $result */
        $result = match ($customerSalesChannel->platform->type) {
            PlatformTypeEnum::SHOPIFY => CheckShopifyChannel::run($customerSalesChannel),
            PlatformTypeEnum::WOOCOMMERCE => CheckWooChannel::run($platformUser),
            PlatformTypeEnum::EBAY => CheckEbayChannel::run($platformUser),
            default => null
        };

        if (!$result) {
            return null;
        }

        return $this->update($customerSalesChannel, [
            'ban_stock_update_util' => $result->platform_status ? null : now()->addDays(3)
        ]);
    }

    public function asController(CustomerSalesChannel $customerSalesChannel, ActionRequest $request): string
    {
        $this->initialisation($request);

        return $this->handle($customerSalesChannel);
    }
}
