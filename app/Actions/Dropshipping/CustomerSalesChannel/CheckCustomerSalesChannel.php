<?php

/*
 *  Author: Raul Perusquia <raul@inikoo.com>
 *  Created: Tue, 30 Aug 2022 13:05:43 Malaysia Time, Kuala Lumpur, Malaysia
 *  Copyright (c) 2022, Raul A Perusquia F
 */

namespace App\Actions\Dropshipping\CustomerSalesChannel;

use App\Actions\Dropshipping\Ebay\CheckEbayChannel;
use App\Actions\Dropshipping\Shopify\CheckShopifyChannel;
use App\Actions\Dropshipping\WooCommerce\CheckWooChannel;
use App\Actions\OrgAction;
use App\Actions\Traits\WithActionUpdate;
use App\Enums\Ordering\Platform\PlatformTypeEnum;
use App\Models\Dropshipping\CustomerSalesChannel;
use Illuminate\Console\Command;
use Lorisleiva\Actions\ActionRequest;

class CheckCustomerSalesChannel extends OrgAction
{
    use WithActionUpdate;


    private CustomerSalesChannel $customerSalesChannel;

    public function handle(CustomerSalesChannel $customerSalesChannel): CustomerSalesChannel
    {
        return match ($customerSalesChannel->platform->type) {
            PlatformTypeEnum::EBAY => CheckEbayChannel::run($customerSalesChannel->user),
            PlatformTypeEnum::SHOPIFY => CheckShopifyChannel::run($customerSalesChannel),
            PlatformTypeEnum::WOOCOMMERCE => CheckWooChannel::run($customerSalesChannel->user),
            default => $customerSalesChannel
        };
    }

    public function action(CustomerSalesChannel $customerSalesChannel, array $modelData, int $hydratorsDelay = 0): CustomerSalesChannel
    {
        $this->asAction             = true;
        $this->customerSalesChannel = $customerSalesChannel;
        $this->hydratorsDelay       = $hydratorsDelay;
        $this->initialisation($customerSalesChannel->organisation, $modelData);

        return $this->handle($customerSalesChannel);
    }

    public function asController(CustomerSalesChannel $customerSalesChannel, ActionRequest $request): CustomerSalesChannel
    {
        $this->customerSalesChannel = $customerSalesChannel;

        $this->initialisationFromShop($customerSalesChannel->shop, $request);

        return $this->handle($customerSalesChannel);
    }

    public string $commandSignature = 'customer-sales-channel:check';

    public function asCommand(Command $command): int
    {
        $success = 0;
        $failed  = 0;
        $lostAccount  = 0;

        CustomerSalesChannel::where('platform_status', false)
            ->whereNull('closed_at')
            ->whereNotNull('platform_user_id')
            ->limit(10)
            ->chunkById(100, function ($inactiveCustomerSalesChannels) use (&$success, &$failed, &$lostAccount) {
                foreach ($inactiveCustomerSalesChannels as $customerSalesChannel) {

                    if(!$customerSalesChannel->user) {
                        $lostAccount++;
                        continue;
                    }

                    $result = $this->handle($customerSalesChannel);

                    $result->refresh();
                    if ($result->platform_status) {
                        $success++;
                    } else {
                        $failed++;
                    }
                }
            });

        $command->table(['Status', 'Count'], [
            ['Inactive', $failed],
            ['Active', $success],
            ['Lost Account', $lostAccount]
        ]);

        return 0;
    }
}
