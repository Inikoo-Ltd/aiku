<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 23 Jul 2025 08:26:56 British Summer Time, Trnava, Slovakia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Dropshipping\WooCommerce\Product;

use App\Models\Dropshipping\WooCommerceUser;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Support\Arr;
use Lorisleiva\Actions\Concerns\AsAction;

class UpdateBatchWooCustomerSalesChannelPortfolio implements ShouldBeUnique
{
    use AsAction;


    public string $jobQueue = 'woo';

    public function getJobUniqueId(WooCommerceUser $wooCommerceUser): string
    {
        return $wooCommerceUser->id;
    }

    public function handle(WooCommerceUser $wooCommerceUser, array $productData): void
    {
        $customerSalesChannel = $wooCommerceUser->customerSalesChannel;

        $stockUpdated = $wooCommerceUser->batchUpdateWooCommerceProducts($productData);

        if (Arr::get($stockUpdated, 'update')) {
            $customerSalesChannel->update([
                'ban_stock_update_util' => null
            ]);
        } else {
            $ban = true;
            $rawMessage = Arr::get($stockUpdated, '0');

            if (is_array($rawMessage)) {
                $rawMessage = json_encode($rawMessage);
            }

            if (is_string($rawMessage)) {
                $messageData = json_decode($rawMessage, true);
                if ($messageData) {
                    if (Arr::get($messageData, 'code') == 'rest_invalid_param' || Arr::get($messageData, 'code') == 'woocommerce_rest_product_invalid_id' || Arr::get($messageData, 'data.status') == 404 || Arr::get($messageData, 'data.status') == 400) {
                        $ban = false;
                    }
                }

                if ($ban) {
                    $customerSalesChannel->update([
                        'ban_stock_update_util' => now()->addSeconds(10)
                    ]);
                }
            }
        }
    }
}
