<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 12 Jul 2025 20:47:59 British Summer Time, Sheffield, UK
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Maintenance\Dropshipping;

use App\Actions\Dropshipping\CustomerSalesChannel\WithExternalPlatforms;
use App\Actions\Traits\WithActionUpdate;
use App\Enums\Dropshipping\CustomerSalesChannelConnectionStatusEnum;
use App\Enums\Ordering\Platform\PlatformTypeEnum;
use App\Models\Dropshipping\CustomerSalesChannel;
use App\Models\Dropshipping\Platform;
use Illuminate\Console\Command;
use Lorisleiva\Actions\Concerns\AsAction;

class RepairShopifyCustomerSalesChannelsConnectionStatus
{
    use AsAction;
    use WithActionUpdate;
    use WithExternalPlatforms;


    public function handle(CustomerSalesChannel $customerSalesChannel, Command $command): void
    {
        list($connectionStatus, $error) = $this->getShopifyConnectionStatus($customerSalesChannel);

        //        $customerSalesChannel->update([
        //            'connection_status' => $connectionStatus
        //        ]);
    }


    public function getCommandSignature(): string
    {
        return 'repair:customer_sales_channels_connection_status';
    }

    public function asCommand(Command $command): void
    {
        $platform = Platform::where('type', PlatformTypeEnum::SHOPIFY)->firstOrFail();

        $tableData = [];
        $counter   = 1;

        /** @var CustomerSalesChannel $customerSalesChannel */
        foreach (CustomerSalesChannel::where('platform_id', $platform->id)->get() as $customerSalesChannel) {
            list($connectionStatus, $errors) = $this->getShopifyConnectionStatus($customerSalesChannel);
            $labels = CustomerSalesChannelConnectionStatusEnum::labels();


            $tableData[] = [
                'counter'           => $counter,
                'channel_name'      => $customerSalesChannel->name,
                'reference'         => $customerSalesChannel->reference.'.myshopify.com '.$customerSalesChannel->platform_user_id,
                'status'            => $customerSalesChannel->status->value,
                'connection_status' => $labels[$connectionStatus->value] ?? $connectionStatus->value,
                'errors'            => $errors
            ];

            $counter++;

            // Uncomment this to actually update the connection status
            // $this->handle($customerSalesChannel, $command);
        }

        $command->table(
            ['#', 'Channel Name', 'Reference', 'Status', 'Connection Status', 'Errors'],
            $tableData
        );
    }

}
