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

    /**
     * @return array{location_key: ?string, moved: int, already: int, failed: array<string, string>}
     */
    public function handle(CustomerSalesChannel $customerSalesChannel): array
    {
        return UpdateEbayUserData::make()->moveToWarehouseLocation($customerSalesChannel->user);
    }

    public function asCommand(Command $command): int
    {
        Nightwatch::dontSample();

        $customerSalesChannel = CustomerSalesChannel::where('slug', $command->argument('customerSalesChannel'))->firstOrFail();

        $result = $this->handle($customerSalesChannel);

        if (!$result['location_key']) {
            $command->error('No warehouse location on eBay for '.$customerSalesChannel->slug);

            return 1;
        }

        $command->info($result['location_key'].': '.$result['moved'].' moved, '.$result['already'].' already there, '.$result['drafts'].' drafts skipped, '.count($result['failed']).' failed');

        foreach ($result['failed'] as $offerId => $error) {
            $command->warn($offerId.' '.$error);
        }

        return $result['failed'] ? 1 : 0;
    }
}
