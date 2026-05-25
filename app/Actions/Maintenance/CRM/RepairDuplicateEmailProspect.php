<?php

/*
 * Author: eka yudinata (https://github.com/ekayudinata)
 * Created: Friday, 22 May 2026 15:40:03 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2026, eka yudinata
 */

namespace App\Actions\Maintenance\CRM;

use App\Enums\Catalogue\Shop\ShopStateEnum;
use App\Enums\Catalogue\Shop\ShopTypeEnum;
use App\Models\Catalogue\Shop;
use Illuminate\Console\Command;
use Lorisleiva\Actions\Concerns\AsAction;
use Throwable;

class RepairDuplicateEmailProspect
{
    use AsAction;

    public function handle(?Shop $shop = null): void
    {
        if ($shop) {
            RepairDuplicatedProspectPerShop::dispatch($shop);
        } else {
            $allShops = Shop::where('state', ShopStateEnum::OPEN)
                ->whereIn('type', [ShopTypeEnum::B2B, ShopTypeEnum::DROPSHIPPING])
                ->get();
            foreach ($allShops as $shopItem) {
                RepairDuplicatedProspectPerShop::dispatch($shopItem);
            }
        }
    }


    public function getCommandSignature(): string
    {
        return 'maintenance:repair_duplicated_prospects {shop_slug?}';
    }

    public function asCommand(Command $command): int
    {
        $shopSlug = $command->argument('shop_slug');
        $shop = null;

        $command->info('Starting repair of duplicated prospects...');

        // If shop_slug is provided, fetch the shop
        if ($shopSlug !== null) {
            $shop = Shop::where('slug', $shopSlug)->where('state', ShopStateEnum::OPEN)->whereIn('type', [ShopTypeEnum::B2B, ShopTypeEnum::DROPSHIPPING])->first();

            if ($shop === null) {
                $command->error("Shop with slug '{$shopSlug}' not found.");
                return 1;
            }

            $command->line("Processing duplicated prospects for shop: {$shop->name}");
        } else {
            $command->line("Processing duplicated prospects for all shops...");
        }

        try {
            $command->info("This will running in background and take a while...");
            $command->info("Run this command to check the progress: php artisan queue:work");

            $this->handle($shop);
            $command->info("Repair completed successfully!");
        } catch (Throwable $e) {
            $command->error($e->getMessage());

            return 1;
        }

        return 0;
    }
}
