<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 12 Jul 2025 20:47:59 British Summer Time, Sheffield, UK
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Maintenance\Dropshipping;

use App\Actions\Dropshipping\Ebay\Product\StoreEbayProduct;
use App\Actions\Traits\WithActionUpdate;
use App\Models\Dropshipping\CustomerSalesChannel;
use App\Models\Dropshipping\EbayUser;
use App\Models\Dropshipping\Portfolio;
use Illuminate\Console\Command;
use Lorisleiva\Actions\Concerns\AsAction;

class RepairEbayProductPublish
{
    use AsAction;
    use WithActionUpdate;

    public function handle(EbayUser $ebayUser, Portfolio $portfolio): void
    {
        StoreEbayProduct::run($ebayUser, $portfolio);
    }

    public function getCommandSignature(): string
    {
        return 'repair:ebay_product {customerSalesChannel} {portfolio}';
    }

    public function asCommand(Command $command): void
    {
        $customerSalesChannel = CustomerSalesChannel::where('slug', $command->argument('customerSalesChannel'))->first();
        $portfolio = Portfolio::where('item_code', $command->argument('portfolio'))->first();

        $this->handle($customerSalesChannel->user, $portfolio);
    }
}
