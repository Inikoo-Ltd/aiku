<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 13 Nov 2026 Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Maintenance\Dropshipping;

use App\Actions\Dropshipping\Ebay\UpdateEbayUserData;
use App\Models\Dropshipping\CustomerSalesChannel;
use Illuminate\Console\Command;
use Laravel\Nightwatch\Facades\Nightwatch;
use Lorisleiva\Actions\Concerns\AsAction;

class RepairEbayLocationAddress
{
    use AsAction;

    public string $commandSignature = 'repair:ebay_location_address {customerSalesChannel}';

    public function handle(CustomerSalesChannel $customerSalesChannel): bool
    {
        return $customerSalesChannel->user && UpdateEbayUserData::make()->updateLocationAddress($customerSalesChannel->user);
    }

    public function asCommand(Command $command): int
    {
        Nightwatch::dontSample();

        $customerSalesChannel = CustomerSalesChannel::where('slug', $command->argument('customerSalesChannel'))->firstOrFail();

        if (!$this->handle($customerSalesChannel)) {
            $command->error('eBay did not accept the address update for '.$customerSalesChannel->slug);

            return 1;
        }

        $command->info('Location '.$customerSalesChannel->user->location_key.' now has the shop warehouse address');

        return 0;
    }
}
