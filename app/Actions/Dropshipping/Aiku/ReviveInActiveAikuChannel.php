<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 19 Jul 2025 09:01:58 British Summer Time, Trnava, Slovakia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Dropshipping\Aiku;

use App\Actions\Dropshipping\CustomerSalesChannel\UpdateCustomerSalesChannel;
use App\Actions\Traits\WithActionUpdate;
use App\Enums\Dropshipping\CustomerSalesChannelStateEnum;
use App\Enums\Dropshipping\CustomerSalesChannelStatusEnum;
use App\Models\Dropshipping\CustomerSalesChannel;
use Illuminate\Console\Command;
use Lorisleiva\Actions\Concerns\AsAction;

class ReviveInActiveAikuChannel
{
    use asAction;
    use WithActionUpdate;

    public string $commandSignature = 'revive:manual_channel {customerSalesChannel?}';

    public function asCommand(Command $command): void
    {
        $customerSalesChannel = CustomerSalesChannel::where('slug', $command->argument('customerSalesChannel'))->firstOrFail();

        $this->handle($customerSalesChannel);
    }

    public function handle(CustomerSalesChannel $customerSalesChannel): void
    {
        UpdateCustomerSalesChannel::run(
            $customerSalesChannel,
            [
                'status' => CustomerSalesChannelStatusEnum::OPEN,
                'name' => preg_replace('/ - deleted - \d{1,2}$/', '', $customerSalesChannel->name),
                'closed_at' => null,
                'state' => CustomerSalesChannelStateEnum::READY,
                'can_connect_to_platform' => true,
                'exist_in_platform' => true,
                'platform_status' => true
            ]
        );
    }
}
