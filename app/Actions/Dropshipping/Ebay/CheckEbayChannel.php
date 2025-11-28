<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 19 Jul 2025 09:01:58 British Summer Time, Trnava, Slovakia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Dropshipping\Ebay;

use App\Actions\Dropshipping\CustomerSalesChannel\UpdateCustomerSalesChannel;
use App\Actions\Traits\WithActionUpdate;
use App\Enums\Dropshipping\CustomerSalesChannelStateEnum;
use App\Enums\Dropshipping\EbayUserStepEnum;
use App\Enums\Ordering\Platform\PlatformTypeEnum;
use App\Models\Dropshipping\CustomerSalesChannel;
use App\Models\Dropshipping\EbayUser;
use App\Models\Dropshipping\Platform;
use Illuminate\Console\Command;
use Lorisleiva\Actions\Concerns\AsAction;

class CheckEbayChannel
{
    use asAction;
    use WithActionUpdate;

    public function handle(EbayUser $ebayUser): CustomerSalesChannel
    {
        $platformStatus = $canConnectToPlatform = $existInPlatform = false;

        if (!$ebayUser->fulfillment_policy_id && !$ebayUser->return_policy_id && !$ebayUser->payment_policy_id && !$ebayUser->location_key) {
            UpdateEbayUserData::run($ebayUser);

            $ebayUser->refresh();
        }

        $step = EbayUserStepEnum::NAME;
        if (! blank($ebayUser->getUser())) {
            $canConnectToPlatform = true;
            $existInPlatform = true;
            $step = EbayUserStepEnum::AUTH;

            if ($ebayUser->fulfillment_policy_id && $ebayUser->return_policy_id && $ebayUser->payment_policy_id && $ebayUser->location_key) {
                $step = EbayUserStepEnum::COMPLETED;
                $platformStatus = true;
            }
        }

        $this->update($ebayUser, [
            'step' => $step
        ]);

        return UpdateCustomerSalesChannel::run($ebayUser->customerSalesChannel, [
            'state' => CustomerSalesChannelStateEnum::AUTHENTICATED,
            'platform_status'         => $platformStatus,
            'can_connect_to_platform' => $canConnectToPlatform,
            'exist_in_platform'       => $existInPlatform
        ]);
    }

    public function getCommandSignature(): string
    {
        return 'ebay:check {customerSalesChannel?}';
    }

    public function asController(CustomerSalesChannel $customerSalesChannel): CustomerSalesChannel
    {
        /** @var EbayUser $ebay */
        $ebay = $customerSalesChannel->user;

        return $this->handle($ebay);
    }

    public function asCommand(Command $command): void
    {
        if ($command->argument('customerSalesChannel')) {
            $customerSalesChannel = CustomerSalesChannel::where('slug', $command->argument('customerSalesChannel'))->firstOrFail();
            $customerSalesChannel = $this->handle($customerSalesChannel->user);

            $this->statusInfo($customerSalesChannel, $command);
        } else {
            $platform = Platform::where('type', PlatformTypeEnum::EBAY)->first();
            foreach (CustomerSalesChannel::where('platform_id', $platform->id)->get() as $customerSalesChannel) {
                if ($customerSalesChannel->user) {
                    /** @var EbayUser $ebay */
                    $ebay = $customerSalesChannel->user;
                    $this->handle($ebay);

                    $this->statusInfo($customerSalesChannel, $command);
                }
            }
        }
    }

    public function statusInfo(CustomerSalesChannel $customerSalesChannel, Command $command): void
    {
        // Display CustomerSalesChannel status information
        $statusData = [
            ['Customer Sales Channel', $customerSalesChannel->slug],
            ['Platform Status', $customerSalesChannel->platform_status ? 'Yes' : 'No'],
            ['Can Connect to Platform', $customerSalesChannel->can_connect_to_platform ? 'Yes' : 'No'],
            ['Exist in Platform', $customerSalesChannel->exist_in_platform ? 'Yes' : 'No']
        ];

        $command->info("\nCustomer Sales Channel Status:");
        $command->table(['Field', 'Value'], $statusData);

        $command->info("\nShop data updated successfully.");
    }
}
