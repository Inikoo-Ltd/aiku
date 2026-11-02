<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 02 Nov 2026 20:15:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Maintenance\Dropshipping;

use App\Actions\Dropshipping\Shopify\FulfilmentService\SyncShopifyDeliveryProfile;
use App\Models\Dropshipping\CustomerSalesChannel;
use App\Models\Dropshipping\ShopifyUser;
use Illuminate\Console\Command;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Between the INI-161 deploy and a7fd45150d, stores still on shipping profiles were given the app's
 * AW Connect shipping profile, so their buyers paid our rates instead of the merchant's (INI-177).
 */
class RepairShopifyMerchantShippingRates
{
    use AsAction;

    public string $commandSignature = 'repair:shopify_merchant_shipping_rates {customerSalesChannels* : Slugs of the channels} {--dry-run : Report what would change without writing}';

    public function asCommand(Command $command): int
    {
        $failed = 0;
        foreach ($command->argument('customerSalesChannels') as $slug) {
            $shopifyUser = CustomerSalesChannel::where('slug', $slug)->first()?->user;
            if (!$shopifyUser instanceof ShopifyUser) {
                $command->line("FAIL $slug No Shopify user on this channel");
                $failed++;
                continue;
            }

            [$status, $message] = SyncShopifyDeliveryProfile::make()->restoreMerchantRates($shopifyUser, (bool) $command->option('dry-run'));
            $command->line(($status ? 'ok   ' : 'FAIL ').$slug.' '.$message);
            $failed += $status ? 0 : 1;
        }

        return $failed ? 1 : 0;
    }
}
