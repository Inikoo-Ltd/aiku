<?php

/*
 * Author: Artha <artha@aw-advantage.com>
 * Created: Fri, 31 Oct 2025 10:21:06 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Maintenance\Dropshipping;

use App\Actions\Retina\Dropshipping\Portfolio\BatchDeleteRetinaPortfolio;
use App\Actions\Traits\WithActionUpdate;
use App\Models\Dropshipping\CustomerSalesChannel;
use Illuminate\Console\Command;
use Lorisleiva\Actions\Concerns\AsAction;

class RepairDeleteCustomerSalesChannelPortfolios
{
    use AsAction;
    use WithActionUpdate;

    /**
     * @throws \Throwable
     */
    public function handle(CustomerSalesChannel $customerSalesChannel): void
    {
        $portfolioIds = $customerSalesChannel->portfolios->pluck('id')->toArray();

        BatchDeleteRetinaPortfolio::run($customerSalesChannel, [
            'portfolios' => $portfolioIds
        ]);
    }

    public function getCommandSignature(): string
    {
        return 'repair:cs_delete_portfolios {customerSalesChannel}';
    }

    public function asCommand(Command $command): void
    {
        $customerSalesChannelSlug = $command->argument('customerSalesChannel');

        if (!blank($customerSalesChannelSlug)) {
            $customerSalesChannel = CustomerSalesChannel::where('slug', $customerSalesChannelSlug)->first();
            $this->handle($customerSalesChannel);
        }
    }
}
