<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 12 Jul 2025 20:47:59 British Summer Time, Sheffield, UK
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Maintenance\Dropshipping;

use App\Actions\Dropshipping\CustomerSalesChannel\CloseCustomerSalesChannelPostActions;
use App\Actions\Dropshipping\CustomerSalesChannel\UpdateCustomerSalesChannel;
use App\Actions\Dropshipping\Shopify\WithShopifyApi;
use App\Actions\Traits\WithActionUpdate;
use App\Enums\Dropshipping\CustomerSalesChannelStatusEnum;
use App\Enums\Ordering\Platform\PlatformTypeEnum;
use App\Models\Dropshipping\CustomerSalesChannel;
use App\Models\Dropshipping\Platform;
use Lorisleiva\Actions\Concerns\AsAction;

class RepairPortfolioDeletedShopifyUsers
{
    use AsAction;
    use WithActionUpdate;
    use WithShopifyApi;

    public function handle(CustomerSalesChannel $customerSalesChannel)
    {
        UpdateCustomerSalesChannel::run(
            $customerSalesChannel,
            [
                'status' => CustomerSalesChannelStatusEnum::CLOSED,
                'name' => $customerSalesChannel->name.' - deleted - '.rand(00, 99),
                'closed_at' => now()
            ]
        );

        CloseCustomerSalesChannelPostActions::dispatch($customerSalesChannel);
    }

    public function getCommandSignature(): string
    {
        return 'repair:shopify_user_deleted_incomplete';
    }

    public function asCommand(): void
    {
        $platform = Platform::where('type', PlatformTypeEnum::SHOPIFY)->first();

        $channels = CustomerSalesChannel::where('platform_id', $platform->id)
        ->where('platform_status', true)
        ->where('status', CustomerSalesChannelStatusEnum::CLOSED)
        ->whereNull('closed_at')
        ->get();

        foreach ($channels as $channel) {
            $this->handle($channel);
        }
    }

}
