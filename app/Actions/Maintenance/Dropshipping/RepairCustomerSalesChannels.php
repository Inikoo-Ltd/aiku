<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 19 Jul 2025 09:01:58 British Summer Time, Trnava, Slovakia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Maintenance\Dropshipping;

use App\Actions\Dropshipping\CustomerSalesChannel\WithExternalPlatforms;
use App\Actions\Traits\WithActionUpdate;
use App\Enums\Dropshipping\CustomerSalesChannelConnectionStatusEnum;
use App\Enums\Ordering\Platform\PlatformTypeEnum;
use App\Models\Dropshipping\CustomerSalesChannel;
use App\Models\Dropshipping\Platform;
use App\Models\Dropshipping\ShopifyUser;
use Lorisleiva\Actions\Concerns\AsAction;

class RepairCustomerSalesChannels
{
    use AsAction;
    use WithActionUpdate;
    use WithExternalPlatforms;


    public function handle(CustomerSalesChannel $customerSalesChannel): array
    {
        // Get shop data to verify connection
        $storeData = $this->getShopifyShopData($customerSalesChannel);
        if (!$storeData) {
            return [false, 'Failed to get shop data'];
        }

        dd($storeData);

        // Create fulfillment service
        list($success, $result) = $this->storeFulfilmentService($customerSalesChannel);

        if (!$success) {
            return [false, $result]; // Return error message
        }

        // Update connection status if needed
        $connectionStatus = $this->getConnectionStatus($customerSalesChannel);

        $customerSalesChannel->update([
            'connection_status' => $connectionStatus
        ]);

        return [true, $result]; // Return fulfillment service data
    }


    public function storeFulfilmentService(CustomerSalesChannel $customerSalesChannel): array
    {

    }

    public function getConnectionStatus(CustomerSalesChannel $customerSalesChannel): CustomerSalesChannelConnectionStatusEnum
    {
        if ($customerSalesChannel->platform->type == PlatformTypeEnum::MANUAL) {
            return CustomerSalesChannelConnectionStatusEnum::NO_APPLICABLE;
        } else {
            $connectionStatus = $customerSalesChannel->connection_status;
            if (!$connectionStatus) {
                $connectionStatus = CustomerSalesChannelConnectionStatusEnum::PENDING;
            }

            if (!$customerSalesChannel->user) {
                return CustomerSalesChannelConnectionStatusEnum::ERROR;
            }

            if (!$this->hasCredentials($customerSalesChannel)) {
                return CustomerSalesChannelConnectionStatusEnum::ERROR;
            }

            $platform = $customerSalesChannel->platform;
            if ($platform->type == PlatformTypeEnum::SHOPIFY) {
                /** @var ShopifyUser $shopifyUser */
                $shopifyUser = $customerSalesChannel->user;

                $isConnectedToShopify = $this->checkIfConnectedToShopify($shopifyUser);
                if ($isConnectedToShopify != null) {
                    $connectionStatus = $isConnectedToShopify;
                }
            }

            return $connectionStatus;
        }
    }


    public function getCommandSignature(): string
    {
        return 'repair:customer_sales_channels';
    }

    public function asCommand(): void
    {
        $platform = Platform::where('type', PlatformTypeEnum::SHOPIFY)->firstOrFail();

        $tableData = [];
        $counter   = 1;

        foreach (CustomerSalesChannel::where('platform_id', $platform->id)->where('id', 35841)->get() as $customerSalesChannel) {
            list($success, $result) = $this->handle($customerSalesChannel);

            $status  = $success ? 'Success' : 'Failed';
            $details = $success
                ? 'Created fulfillment service: '.($result['name'] ?? 'Unknown').' (ID: '.($result['id'] ?? 'N/A').')'
                : 'Error: '.$result;

            $tableData[] = [
                'counter'      => $counter,
                'channel_name' => $customerSalesChannel->name,
                'reference'    => $customerSalesChannel->reference.'.myshopify.com',
                'status'       => $status,
                'details'      => $details
            ];

            $counter++;
        }

        if (empty($tableData)) {
            echo "No Shopify customer sales channels found.\n";

            return;
        }

        // Output results in table format
        $this->table(
            ['#', 'Channel Name', 'Reference', 'Status', 'Details'],
            $tableData
        );
    }

    /**
     * Display a table in the console.
     */
    protected function table(array $headers, array $rows): void
    {
        $width = [];

        // Calculate column widths
        foreach ($headers as $i => $header) {
            $width[$i] = strlen($header);
            foreach ($rows as $row) {
                $width[$i] = max($width[$i], strlen($row[$header] ?? ''));
            }
        }

        // Output headers
        echo "\n";
        foreach ($headers as $i => $header) {
            echo str_pad($header, $width[$i] + 2);
        }
        echo "\n";

        // Output separator
        foreach ($width as $w) {
            echo str_repeat('-', $w + 2);
        }
        echo "\n";

        // Output rows
        foreach ($rows as $row) {
            foreach ($headers as $i => $header) {
                echo str_pad($row[$header] ?? '', $width[$i] + 2);
            }
            echo "\n";
        }
        echo "\n";
    }

}
