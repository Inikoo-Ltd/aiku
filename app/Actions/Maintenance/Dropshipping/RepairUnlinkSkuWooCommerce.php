<?php

/*
 * Author: Artha <artha@aw-advantage.com>
 * Created: Fri, 31 Oct 2025 10:21:06 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Maintenance\Dropshipping;

use App\Actions\Retina\Dropshipping\Portfolio\UnlinkRetinaPortfolio;
use App\Actions\Traits\WithActionUpdate;
use App\Models\Dropshipping\CustomerSalesChannel;
use Illuminate\Console\Command;
use Lorisleiva\Actions\Concerns\AsAction;
use Laravel\Nightwatch\Facades\Nightwatch;

class RepairUnlinkSkuWooCommerce
{
    use AsAction;
    use WithActionUpdate;

    public function handle(CustomerSalesChannel $customerSalesChannel): void
    {
        foreach ($customerSalesChannel->portfolios as $portfolio) {
            UnlinkRetinaPortfolio::run($portfolio);

            echo "🤘🏻 Success to Unlink Product " . "\n";
        }
    }

    public function getCommandSignature(): string
    {
        return 'repair:woo_unlink_product {customerSalesChannel}';
    }

    public function asCommand(Command $command): void
    {
        Nightwatch::dontSample();
        $customerSalesChannelSlug = $command->argument('customerSalesChannel');
        $customerSalesChannel = CustomerSalesChannel::where('slug', $customerSalesChannelSlug)->first();

        $this->handle($customerSalesChannel);
    }
}
