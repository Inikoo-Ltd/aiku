<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 12 Jul 2025 20:47:59 British Summer Time, Sheffield, UK
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Maintenance\Dropshipping;

use App\Actions\Dropshipping\Shopify\FulfilmentService\DeleteFulfilmentService;
use App\Actions\Dropshipping\Shopify\WithShopifyApi;
use App\Actions\Traits\WithActionUpdate;
use App\Models\Dropshipping\ShopifyUser;
use Illuminate\Console\Command;
use Lorisleiva\Actions\Concerns\AsAction;

class RepairFulfillmentLocationsDeletedShopifyUsers
{
    use AsAction;
    use WithActionUpdate;
    use WithShopifyApi;

    public function handle(ShopifyUser $shopifyUser, Command $command)
    {
        list($status, $response) = DeleteFulfilmentService::run($shopifyUser->customerSalesChannel, $shopifyUser->shopify_fulfilment_service_id);

        $encoded = json_encode($response);
        $command->info("Deleted fulfilment locations from Shopify: $status, data: $encoded \n");
    }

    public function getCommandSignature(): string
    {
        return 'repair:FulfillmentLocationsDeletedShopifyUsers';
    }

    public function asCommand(Command $command): void
    {
        foreach (ShopifyUser::withTrashed()
                     ->whereNotNull('deleted_at')
                     ->get() as $shopifyUser) {
            $this->handle($shopifyUser, $command);
        }
    }

}
