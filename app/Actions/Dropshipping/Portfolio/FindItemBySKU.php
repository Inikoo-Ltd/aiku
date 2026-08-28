<?php

/*
 * Author: Artha <artha@aw-advantage.com>
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Dropshipping\Portfolio;

use App\Models\Catalogue\Product;
use App\Models\Catalogue\Shop;
use App\Models\Fulfilment\StoredItem;
use Illuminate\Console\Command;
use Lorisleiva\Actions\Concerns\AsAction;

class FindItemBySKU
{
    use AsAction;
    use WithPortfolioSKU;

    public string $commandSignature = 'dropshipping:portfolio:find-by-sku {sku} {--shop= : Shop slug to search in} {--type= : Product or StoredItem}';

    public function handle(string $sku, ?Shop $shop = null, ?string $itemType = null): Product|StoredItem|null
    {
        return $this->findItemBySKU($sku, $shop, $itemType);
    }

    public function asCommand(Command $command): int
    {
        $shop = null;

        if ($shopSlug = $command->option('shop')) {
            $shop = Shop::where('slug', $shopSlug)->first();

            if (!$shop) {
                $command->error("Shop $shopSlug not found");

                return 1;
            }
        }

        $item = $this->handle($command->argument('sku'), $shop, $command->option('type'));

        if (!$item) {
            $command->warn('No item found for this SKU');

            return 1;
        }

        $command->info(class_basename($item)." $item->id: $item->name");
        $command->table(
            ['Field', 'Value'],
            [
                ['Type', class_basename($item)],
                ['Id', $item->id],
                ['Slug', $item->slug],
                ['Code', $item instanceof StoredItem ? $item->reference : $item->code],
                ['Name', $item->name],
                ['SKU', $this->getSKU($item)],
            ]
        );

        return 0;
    }
}
