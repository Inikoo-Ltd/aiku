<?php

/*
 * Author: Eka Yudinata <ekayudinata@gmail.com>
 * Created: Thu, 19 Dec 2025 18:08:10 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Eka Yudinata
 */

namespace App\Actions\Comms\Outbox\BackToStockNotification;

use Illuminate\Contracts\Queue\ShouldQueue;
use Lorisleiva\Actions\Concerns\AsAction;
use App\Models\Catalogue\Product;
use App\Services\QueryBuilder;
use App\Models\CRM\BackInStockReminder;
use Illuminate\Console\Command;

class TestingUpdateProductStock implements ShouldQueue
{
    use AsAction;
    /*
     * NOTE: ONLY FOR TESTING IN LOCAL
     *
     */
    public string $commandSignature = 'testing:update-product-stock';


    public function handle(): void
    {
        $queryOutbox = QueryBuilder::for(BackInStockReminder::class);
        $queryOutbox->select('back_in_stock_reminders.product_id');
        $productIds = $queryOutbox->pluck('product_id');
        \Log::info("Product Ids that stock updated: ".$productIds);
        // update product Stock
        Product::whereIn('id', $productIds)
            ->update([
                'available_quantity' => 1,
                'back_in_stock_since' => now()
            ]);
    }

    public function asCommand(Command $command): void
    {
        // Check environment
        if (!app()->environment('local')) {
            $command->error('⚠️  This command can only be run in local environment!');
            return;
        }

        // Show warning
        $command->warn('⚠️  WARNING: This will update product stock for all back-in-stock reminders!');

        // Ask for confirmation
        if (!$command->confirm('Are you sure you want to run this command?', false)) {
            $command->info('Command cancelled.');
            return;
        }

        // Run the command
        $this->handle();
        $command->info('✓ Product stock updated successfully!');
    }
}
