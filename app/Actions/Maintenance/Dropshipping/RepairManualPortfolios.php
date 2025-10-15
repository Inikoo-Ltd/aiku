<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 12 Jul 2025 20:47:59 British Summer Time, Sheffield, UK
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Maintenance\Dropshipping;

use App\Actions\Dropshipping\Portfolio\UpdatePortfolio;
use App\Actions\Traits\WithActionUpdate;
use App\Models\Dropshipping\CustomerSalesChannel;
use App\Models\Dropshipping\Portfolio;
use Illuminate\Console\Command;
use Lorisleiva\Actions\Concerns\AsAction;

class RepairManualPortfolios
{
    use AsAction;
    use WithActionUpdate;

    public function handle(CustomerSalesChannel $customerSalesChannel, Portfolio $portfolio): void
    {
        UpdatePortfolio::run($portfolio, [
            'status' => true,
            'has_valid_platform_product_id' => true,
            'exist_in_platform' => true,
            'platform_status' => true
        ]);
    }

    public function getCommandSignature(): string
    {
        return 'repair:manual_products {customerSalesChannel}';
    }

    public function asCommand(Command $command): void
    {
        $customerSalesChannel = CustomerSalesChannel::where('slug', $command->argument('customerSalesChannel'))->first();
        $portfolios = Portfolio::where('customer_sales_channel_id', $customerSalesChannel->id)->get();

        foreach ($portfolios as $portfolio) {
            $this->handle($customerSalesChannel, $portfolio);
        }
    }
}
