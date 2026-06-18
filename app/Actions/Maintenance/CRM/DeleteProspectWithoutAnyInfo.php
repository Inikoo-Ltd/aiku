<?php

/*
 * Author: eka yudinata (https://github.com/ekayudinata)
 * Created: Friday, 17 October 2025 15:40:03 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2025, eka yudinata
 */

namespace App\Actions\Maintenance\CRM;

use App\Enums\Catalogue\Shop\ShopStateEnum;
use App\Enums\Catalogue\Shop\ShopTypeEnum;
use App\Models\Catalogue\Shop;
use App\Models\CRM\Prospect;
use Illuminate\Console\Command;
use Lorisleiva\Actions\Concerns\AsAction;
use Throwable;

class DeleteProspectWithoutAnyInfo
{
    use AsAction;

    public function handle(?Shop $shop = null): void
    {
        $deleted = Prospect::whereNull('name')
            ->whereNull('contact_name')
            ->whereNull('email')
            ->whereNull('phone')
            ->whereNull('company_name')
            ->whereNull('contact_website');

        if ($shop) {
            $deleted->where('shop_id', $shop->id);
        }

        $deleted->delete();
    }


    public function getCommandSignature(): string
    {
        return 'maintenance:delete_prospect_without_any_info {shop_slug?}';
    }

    public function asCommand(Command $command): int
    {
        $shopSlug = $command->argument('shop_slug');
        $shop = null;

        $command->info('Starting deletion of prospects without any info...');

        if ($shopSlug !== null) {
            $shop = Shop::where('slug', $shopSlug)->where('state', ShopStateEnum::OPEN)->whereIn('type', [ShopTypeEnum::B2B, ShopTypeEnum::DROPSHIPPING])->first();

            if ($shop === null) {
                $command->error("Shop with slug '{$shopSlug}' not found.");
                return 1;
            }

            $command->line("Processing prospects without any info for shop: {$shop->name}");
        } else {
            $command->line("Processing prospects without any info for all shops...");
        }

        try {
            $this->handle($shop);
            $command->info("Deletion completed successfully!");
        } catch (Throwable $e) {
            $command->error($e->getMessage());

            return 1;
        }

        return 0;
    }
}
