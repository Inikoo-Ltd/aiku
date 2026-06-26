<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 27 Jul 2025 13:37:25 Central European Summer Time, Trnava, Slovakia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Dropshipping\Ebay;

use App\Actions\Dropshipping\Portfolio\StorePortfolio;
use App\Actions\Dropshipping\Portfolio\UpdatePortfolio;
use App\Actions\Traits\WithActionUpdate;
use App\Models\Catalogue\Product;
use App\Models\Dropshipping\CustomerSalesChannel;
use App\Models\Dropshipping\EbayUser;
use App\Models\Dropshipping\Portfolio;
use Illuminate\Console\Command;
use Illuminate\Support\Arr;
use Lorisleiva\Actions\Concerns\AsAction;

class RepairEbayMismatchPortfolioData
{
    use AsAction;
    use WithActionUpdate;


    public function getCommandSignature(): string
    {
        return 'portfolio:repair_ebay_data {customerSalesChannel}';
    }

    public function handle(CustomerSalesChannel $customerSalesChannel, Command $command): void
    {
        /** @var EbayUser $ebayUser */
        $ebayUser = $customerSalesChannel->user;
         $portfolios = $customerSalesChannel->portfolios;

        foreach ($portfolios as $portfolio) {
            $portfolioEbay = $ebayUser->getOffer($portfolio->platform_product_id);

            if(Arr::get($portfolioEbay, 'availableQuantity') === 1){
                dd($portfolioEbay);
            }
        }
    }

    public function asCommand(Command $command): void
    {
        $customerSalesChannel = CustomerSalesChannel::where('slug', $command->argument('customerSalesChannel'))
            ->first();

        $this->handle($customerSalesChannel, $command);
    }
}
