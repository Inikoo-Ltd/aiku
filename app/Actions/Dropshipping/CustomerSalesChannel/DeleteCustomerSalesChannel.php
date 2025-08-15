<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 11 Jul 2025 15:18:19 British Summer Time, Sheffield, UK
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Dropshipping\CustomerSalesChannel;

use App\Actions\Dropshipping\Amazon\DeleteAmazonUser;
use App\Actions\Dropshipping\CustomerClient\DeleteCustomerClient;
use App\Actions\Dropshipping\Magento\DeleteMagentoUser;
use App\Actions\Dropshipping\Portfolio\DeletePortfolio;
use App\Actions\Dropshipping\ShopifyUser\DeleteShopifyUser;
use App\Actions\Dropshipping\WooCommerce\DeleteWooCommerceUser;
use App\Actions\OrgAction;
use App\Enums\Dropshipping\CustomerSalesChannelStatusEnum;
use App\Enums\Ordering\Platform\PlatformTypeEnum;
use App\Models\Dropshipping\CustomerSalesChannel;
use Illuminate\Console\Command;
use Lorisleiva\Actions\ActionRequest;

class DeleteCustomerSalesChannel extends OrgAction
{
    public function handle(CustomerSalesChannel $customerSalesChannel): ?bool
    {
        if ($customerSalesChannel->user) {
            match ($customerSalesChannel->platform->type) {
                PlatformTypeEnum::SHOPIFY => DeleteShopifyUser::run($customerSalesChannel->user),
                PlatformTypeEnum::WOOCOMMERCE => DeleteWooCommerceUser::run($customerSalesChannel->user),
                PlatformTypeEnum::MAGENTO => DeleteMagentoUser::run($customerSalesChannel->user),
                PlatformTypeEnum::AMAZON => DeleteAmazonUser::run($customerSalesChannel->user),
                default => null
            };
        }

        $numberOrders = $customerSalesChannel->orders()->count();
        if ($numberOrders > 0) {
            UpdateCustomerSalesChannel::run(
                $customerSalesChannel,
                [
                    'status' => CustomerSalesChannelStatusEnum::CLOSED,
                    'closed_at' => now()
                ]
            );

            return false;
        } else {
            foreach ($customerSalesChannel->clients as $client) {
                DeleteCustomerClient::run($client);
            }

            foreach ($customerSalesChannel->portfolios as $portfolio) {
                DeletePortfolio::run($portfolio);
            }

            return $customerSalesChannel->delete();
        }
    }


    public function asController(CustomerSalesChannel $customerSalesChannel, ActionRequest $request)
    {
        $this->initialisationFromShop($customerSalesChannel->shop, $request);

        $this->handle($customerSalesChannel);
    }

    public string $commandSignature = 'delete:customer-sales-channel  {customer_sales_channel}';


    public function asCommand(Command $command): int
    {
        try {
            $customerSalesChannel = CustomerSalesChannel::where('slug', $command->argument('customer_sales_channel'))->firstOrFail();
            $this->handle($customerSalesChannel);
            $command->info('Customer sales channel deleted');

            return 0;
        } catch (\Exception $e) {
            $command->error($e->getMessage());

            return 1;
        }
    }


}
