<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 23 Jul 2025 09:25:51 British Summer Time, Trnava, Slovakia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Dropshipping\Shopify\FulfilmentService;

use App\Models\Dropshipping\CustomerSalesChannel;
use Lorisleiva\Actions\Concerns\AsAction;

class GetFulfilmentServiceName
{
    use AsAction;

    public function handle(CustomerSalesChannel $customerSalesChannel): string
    {
        return 'aiku-'.$customerSalesChannel->shop->slug.' ('.$customerSalesChannel->slug.')';
    }
}
