<?php

/*
 * Author: Ganes <gustiganes@gmail.com>
 * Created on: 28-04-2025, Bali, Indonesia
 * Github: https://github.com/Ganes556
 * Copyright: 2025
 *
*/

namespace App\Actions\Dropshipping\CustomerSalesChannel\Hydrators;

use App\Actions\Traits\WithEnumStats;
use App\Models\Dropshipping\CustomerSalesChannel;
use App\Models\Dropshipping\Portfolio;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Lorisleiva\Actions\Concerns\AsAction;

class CustomerSalesChannelsHydratePortfolios implements ShouldBeUnique
{
    use AsAction;
    use WithEnumStats;

    public function getJobUniqueId(CustomerSalesChannel $customerSalesChannel): string
    {
        return $customerSalesChannel->id;
    }

    public function handle(CustomerSalesChannel $customerSalesChannel): void
    {
        if ($customerSalesChannel->customer_id && $customerSalesChannel->platform_id) {
            $stats['number_portfolios'] = Portfolio::where('customer_sales_channel_id', $customerSalesChannel->id)->count();
            $customerSalesChannel->update($stats);
        }
    }

}
