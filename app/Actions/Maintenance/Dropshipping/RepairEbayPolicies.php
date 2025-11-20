<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 12 Jul 2025 20:47:59 British Summer Time, Sheffield, UK
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Maintenance\Dropshipping;

use App\Actions\Traits\WithActionUpdate;
use App\Enums\Ordering\Platform\PlatformTypeEnum;
use App\Models\Dropshipping\CustomerSalesChannel;
use App\Models\Dropshipping\EbayUser;
use App\Models\Dropshipping\Platform;
use Illuminate\Console\Command;
use Illuminate\Support\Arr;
use Lorisleiva\Actions\Concerns\AsAction;

class RepairEbayPolicies
{
    use AsAction;
    use WithActionUpdate;

    public function handle(EbayUser $ebayUser): void
    {
        $fulfillmentPolicyId = Arr::get($ebayUser->settings, 'defaults.main_fulfilment_policy_id');
        $mainLocationKey = Arr::get($ebayUser->settings, 'defaults.main_location_key');
        $returnPolicyId = Arr::get($ebayUser->settings, 'defaults.main_return_policy_id');
        $paymentPolicyId = Arr::get($ebayUser->settings, 'defaults.main_payment_policy_id');

        $this->update($ebayUser, [
            'fulfillment_policy_id' => $fulfillmentPolicyId,
            'payment_policy_id' => $paymentPolicyId,
            'return_policy_id' => $returnPolicyId,
            'location_key' => $mainLocationKey
        ]);
    }

    public function getCommandSignature(): string
    {
        return 'repair:ebay_policies {customerSalesChannel?}';
    }

    public function asCommand(Command $command): void
    {
        $platform = Platform::where('type', PlatformTypeEnum::EBAY)->first();
        if ($command->argument('customerSalesChannel')) {
            $customerSalesChannels = CustomerSalesChannel::where('slug', $command->argument('customerSalesChannel'))->get();
        } else {
            $customerSalesChannels = CustomerSalesChannel::where('platform_id', $platform->id)->get();
        }

        foreach ($customerSalesChannels as $customerSalesChannel) {
            if ($user = $customerSalesChannel->user) {
                $this->handle($user);
            }
        }
    }
}
