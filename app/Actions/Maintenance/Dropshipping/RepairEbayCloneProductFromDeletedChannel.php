<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 12 Jul 2025 20:47:59 British Summer Time, Sheffield, UK
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Maintenance\Dropshipping;

use App\Actions\Dropshipping\Portfolio\StorePortfolio;
use App\Actions\Traits\WithActionUpdate;
use App\Models\Dropshipping\CustomerSalesChannel;
use Illuminate\Console\Command;
use Lorisleiva\Actions\Concerns\AsAction;

class RepairEbayCloneProductFromDeletedChannel
{
    use AsAction;
    use WithActionUpdate;

    public function handle(CustomerSalesChannel $fromCustomerSalesChannel, CustomerSalesChannel $toCustomerSalesChannel): void
    {
        foreach ($fromCustomerSalesChannel->portfolios as $portfolio) {
            StorePortfolio::run($toCustomerSalesChannel, $portfolio->item, [
                'platform_product_id' => $portfolio->platform_product_id,
                'platform_product_variant_id' => $portfolio->platform_product_variant_id
            ]);
        }
    }

    public function getCommandSignature(): string
    {
        return 'repair:ebay_clone_product {fromCustomerSalesChannel} {toCustomerSalesChannel}';
    }

    public function asCommand(Command $command): void
    {
        $fromCustomerSalesChannel = CustomerSalesChannel::where('slug', $command->argument('fromCustomerSalesChannel'))
            ->withTrashed()
            ->first();

        $toCustomerSalesChannel = CustomerSalesChannel::where('slug', $command->argument('toCustomerSalesChannel'))
            ->first();

        $this->handle($fromCustomerSalesChannel, $toCustomerSalesChannel);
    }
}
