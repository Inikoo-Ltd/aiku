<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 27 Jul 2025 13:37:25 Central European Summer Time, Trnava, Slovakia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Dropshipping\Shopify\Product;

use App\Actions\Traits\WithActionUpdate;
use App\Enums\Ordering\Platform\PlatformTypeEnum;
use App\Models\Dropshipping\CustomerClient;
use App\Models\Dropshipping\Platform;
use Illuminate\Console\Command;
use Lorisleiva\Actions\Concerns\AsAction;

class RepairShopifyCustomerClientIdMigration
{
    use AsAction;
    use WithActionUpdate;


    public function getCommandSignature(): string
    {
        return 'shopify:repair_cc_id';
    }

    public function handle(CustomerClient $customerClient, Command $command): void
    {
        $customerClient = $this->update($customerClient, [
            'reference' => trim($customerClient->name),
            'platform_customer_id' => $customerClient->reference
        ]);

        if ($customerClient->platform_customer_id) {
            $command->info("Successfully update platform customer id $customerClient->name.\n");
        }
    }

    public function asCommand(Command $command): void
    {
        $customerClients = CustomerClient::where('platform_id', Platform::where('type', PlatformTypeEnum::SHOPIFY)->first()->id)
            ->get();

        foreach ($customerClients as $customerClient) {
            $this->handle($customerClient, $command);
        }
    }
}
