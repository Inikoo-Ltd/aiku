<?php

/*
 * Author: Eka Yudinata <ekayudinatha@gmail.com>
 * Created on: 08-05-2025, Bali, Indonesia
 * Github: https://github.com/ekayudinatha
 * Copyright: 2025
 *
*/

namespace App\Actions\Retina\Dropshipping\Portfolio;

use App\Models\Dropshipping\CustomerSalesChannel;
use App\Models\Dropshipping\Portfolio;
use Lorisleiva\Actions\Concerns\AsAction;

class TotalImagePortfolios
{
    use AsAction;

    /**
     * @param CustomerSalesChannel $customerSalesChannel
     * @return int
     */
    public function handle(CustomerSalesChannel $customerSalesChannel): int
    {
        return Portfolio::where('customer_sales_channel_id', $customerSalesChannel->id)->count();
    }
}
