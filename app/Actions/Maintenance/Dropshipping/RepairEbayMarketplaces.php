<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 12 Jul 2025 20:47:59 British Summer Time, Sheffield, UK
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Maintenance\Dropshipping;

use App\Actions\Dropshipping\Ebay\CheckEbayChannel;
use App\Actions\Traits\WithActionUpdate;
use App\Enums\Ordering\Platform\PlatformTypeEnum;
use App\Models\Dropshipping\CustomerSalesChannel;
use App\Models\Dropshipping\EbayUser;
use App\Models\Dropshipping\Platform;
use Illuminate\Console\Command;
use Illuminate\Support\Arr;
use Lorisleiva\Actions\Concerns\AsAction;

class RepairEbayMarketplaces
{
    use AsAction;
    use WithActionUpdate;

    public function handle(EbayUser $ebayUser): void
    {
        if (! $ebayUser->marketplace) {
            $this->update($ebayUser, [
                'marketplace' => Arr::get($ebayUser->customer?->shop?->settings, 'ebay.marketplace_id'),
            ]);
        }

        $response = CheckEbayChannel::run($ebayUser);

        echo ($response->platform_status === true ? "OK" : "NO") . " == ".$response->user->step->value." == " . $response->name . "\n";
    }

    public function getCommandSignature(): string
    {
        return 'repair:ebay_marketplaces {customerSalesChannel?}';
    }

    public function asCommand(Command $command): void
    {
        $platform = Platform::where('type', PlatformTypeEnum::EBAY)->first();
        if ($command->argument('customerSalesChannel')) {
            $customerSalesChannels = CustomerSalesChannel::where('slug', $command->argument('customerSalesChannel'))->get();
        } else {
            $customerSalesChannels = CustomerSalesChannel::where('platform_id', $platform->id)
                ->whereNull('closed_at')
                ->get();
        }

        foreach ($customerSalesChannels as $customerSalesChannel) {
            if ($user = $customerSalesChannel->user) {
                $this->handle($user);
            }
        }
    }
}
