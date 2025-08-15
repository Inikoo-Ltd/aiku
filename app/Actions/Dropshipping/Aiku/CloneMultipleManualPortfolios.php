<?php

/*
 * author Arya Permana - Kirin
 * created on 14-04-2025-16h-43m
 * github: https://github.com/KirinZero0
 * copyright 2025
*/

namespace App\Actions\Dropshipping\Aiku;

use App\Actions\OrgAction;
use App\Actions\Traits\WithActionUpdate;
use App\Models\Dropshipping\CustomerSalesChannel;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;
use Lorisleiva\Actions\Concerns\WithAttributes;

class CloneMultipleManualPortfolios extends OrgAction
{
    use AsAction;
    use WithAttributes;
    use WithActionUpdate;

    /**
     * @throws \Throwable
     */
    public function handle(CustomerSalesChannel $sourceCustomerSalesChannel, CustomerSalesChannel $targetCustomerSalesChannel): void
    {
        CloneFromChannelToChannel::dispatch($sourceCustomerSalesChannel, $targetCustomerSalesChannel);

    }

    /**
     * @throws \Throwable
     */
    public function asController(CustomerSalesChannel $customerSalesChannel, CustomerSalesChannel $targetCustomerSalesChannel, ActionRequest $request): void
    {
        $this->initialisationFromShop($customerSalesChannel->shop, $request);

        $this->handle($customerSalesChannel, $targetCustomerSalesChannel);
    }
}
