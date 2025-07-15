<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 10 Jun 2024 19:36:35 Central European Summer Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Dropshipping\Portfolio;

use App\Actions\OrgAction;
use App\Models\Dropshipping\CustomerSalesChannel;
use App\Models\Dropshipping\Portfolio;
use Illuminate\Console\Command;

class PortfolioCheckPlatformConnection extends OrgAction
{
    public string $commandSignature = 'check:portfolio-connection {portfolio}';

    public function handle(CustomerSalesChannel $customerSalesChannel, Portfolio $portfolio): bool
    {
        // TODO: Ebay, Magento, Amazon
        $platformProductId = $portfolio->platform_product_id;

        return (bool) $customerSalesChannel->user?->checkPortfolioAvailability($platformProductId);
    }

    public function asCommand(Command $command): void
    {
        $portfolio = Portfolio::find($command->argument('portfolio'));

        $this->handle($portfolio->customerSalesChannel, $portfolio);
    }
}
