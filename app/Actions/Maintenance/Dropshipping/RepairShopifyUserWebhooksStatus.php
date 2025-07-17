<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 12 Jul 2025 20:47:59 British Summer Time, Sheffield, UK
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Maintenance\Dropshipping;

use App\Actions\Dropshipping\CustomerSalesChannel\WithExternalPlatforms;
use App\Actions\Dropshipping\Shopify\Webhook\StoreWebhooksToShopify;
use App\Actions\Traits\WithActionUpdate;
use App\Enums\Dropshipping\CustomerSalesChannelStatusEnum;
use App\Enums\Ordering\Platform\PlatformTypeEnum;
use App\Models\Dropshipping\CustomerSalesChannel;
use App\Models\Dropshipping\Platform;
use Illuminate\Console\Command;
use Lorisleiva\Actions\Concerns\AsAction;

class RepairShopifyUserWebhooksStatus
{
    use AsAction;
    use WithActionUpdate;
    use WithExternalPlatforms;


    public function getCommandSignature(): string
    {
        return 'repair:shopify_webhooks';
    }

    public function asCommand(Command $command): void
    {
        $platform = Platform::where('type', PlatformTypeEnum::SHOPIFY)->firstOrFail();


        /** @var CustomerSalesChannel $customerSalesChannel */
        foreach (CustomerSalesChannel::where('platform_id', $platform->id)->where('status', CustomerSalesChannelStatusEnum::OPEN)->get() as $customerSalesChannel) {
            $hasWebhooks = $this->hasCredentials($customerSalesChannel);
            if (!$hasWebhooks) {
                if (!$customerSalesChannel->user) {
                    $command->info('No user for '.$customerSalesChannel->name);
                } else {
                    $command->info('No webhooks for '.$customerSalesChannel->name.' '.$customerSalesChannel->id.' '.$customerSalesChannel->platform_user_id);
                    StoreWebhooksToShopify::run($customerSalesChannel->user);
                    exit();
                }
            }
        }
    }

}
