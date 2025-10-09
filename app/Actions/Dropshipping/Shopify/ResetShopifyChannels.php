<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 25 Jul 2025 09:34:01 British Summer Time, Trnava, Slovakia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Dropshipping\Shopify;

use App\Enums\Ordering\Platform\PlatformTypeEnum;
use App\Models\Dropshipping\CustomerSalesChannel;
use App\Models\Dropshipping\Platform;
use Illuminate\Console\Command;
use Lorisleiva\Actions\Concerns\AsAction;

class ResetShopifyChannels
{
    use AsAction;

    public function handle(): int
    {
        $shopifyPlatform = Platform::where('type', PlatformTypeEnum::SHOPIFY)->first();

        if (!$shopifyPlatform) {
            return 0;
        }

        $customerSalesChannels = CustomerSalesChannel::where('platform_id', $shopifyPlatform->id)
            ->where('platform_status', false)
            ->get();

        $count = 0;
        foreach ($customerSalesChannels as $customerSalesChannel) {
            ResetShopifyChannel::run($customerSalesChannel);
            $count++;
        }

        return $count;
    }

    public function getCommandSignature(): string
    {
        return 'shopify:reset-channels';
    }

    public function getCommandDescription(): string
    {
        return 'Reset all Shopify channels with platform_status = false';
    }

    public function asCommand(Command $command): void
    {
        $shopifyPlatform = Platform::where('type', PlatformTypeEnum::SHOPIFY)->first();

        if (!$shopifyPlatform) {
            $command->info('No Shopify platform found.');
            return;
        }

        $customerSalesChannels = CustomerSalesChannel::where('platform_id', $shopifyPlatform->id)
            ->where('platform_status', false)
            ->get();

        $count = $customerSalesChannels->count();

        if ($count === 0) {
            $command->info('No Shopify channels with platform_status = false found.');
            return;
        }

        $command->info("Found $count Shopify channels with platform_status = false.");

        // Create a progress bar
        $progressBar = $command->getOutput()->createProgressBar($count);
        $progressBar->setFormat(' %current%/%max% [%bar%] %percent:3s%% %elapsed:6s%/%estimated:-6s% %memory:6s%');
        $progressBar->start();

        $successCount = 0;
        $failedChannels = [];

        $isVerbose = true;

        /** @var CustomerSalesChannel $customerSalesChannel */
        foreach ($customerSalesChannels as $customerSalesChannel) {
            if ($isVerbose) {
                $progressBar->clear();
                $command->info("Resetting channel: $customerSalesChannel->slug (ID: $customerSalesChannel->id)");
                $progressBar->display();
            }

            try {
                ResetShopifyChannel::run($customerSalesChannel);
                $successCount++;

                if ($isVerbose) {
                    $progressBar->clear();
                    $command->info("✓ Successfully reset channel: $customerSalesChannel->slug");
                    $progressBar->display();
                }
            } catch (\Exception $e) {
                $failedChannels[] = [
                    'slug' => $customerSalesChannel->slug,
                    'error' => $e->getMessage()
                ];

                if ($isVerbose) {
                    $progressBar->clear();
                    $command->error("✗ Failed to reset channel: $customerSalesChannel->slug");
                    $command->line("  Error: {$e->getMessage()}");
                    $progressBar->display();
                }
            }

            $progressBar->advance();
        }

        $progressBar->finish();
        $command->newLine(2);

        $command->info("Successfully reset $successCount Shopify channels.");

        if (!empty($failedChannels)) {
            $command->error("Failed to reset " . count($failedChannels) . " channels:");
            $command->table(['Channel', 'Error'], array_map(function ($item) {
                return [$item['slug'], $item['error']];
            }, $failedChannels));
        }
    }
}
