<?php
/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 17 Jul 2025 23:29:37 British Summer Time, Trnava, Slovakia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Maintenance\Dropshipping;

use App\Actions\Dropshipping\Portfolio\StorePortfolio;
use App\Models\Catalogue\Product;
use App\Models\Dropshipping\Portfolio;
use App\Models\Fulfilment\StoredItem;
use Illuminate\Console\Command;
use Lorisleiva\Actions\Concerns\AsAction;

class RepairPortfoliosSKUBarcodes
{
    use asAction;

    public function handle(Portfolio $portfolio): Portfolio
    {
        /** @var Product|StoredItem $item */
        $item = $portfolio->item;


        $portfolio->update(
            [
                'sku'     => StorePortfolio::make()->getSku($item),
                'barcode' => $item->barcode,
            ]
        );

        return $portfolio;
    }

    public function getCommandSignature(): string
    {
        return 'repair:portfolios_sku_barcodes';
    }

    public function asCommand(Command $command): void
    {
        $totalPortfolios = Portfolio::whereNull('sku')->count();
        $processedCount = 0;

        $command->info("Starting to repair $totalPortfolios portfolios...");

        Portfolio::whereNull('sku')->chunkById(1000, function ($portfolios) use ($command, &$processedCount, $totalPortfolios) {
            foreach ($portfolios as $portfolio) {
                $portfolio = $this->handle($portfolio);
                $processedCount++;
                $percentage = round(($processedCount / $totalPortfolios) * 100, 2);
            }
            $command->info("[$processedCount/$totalPortfolios] ($percentage%) - {$portfolio->sku} {$portfolio->barcode}");

        });

        $command->info("Completed repairing $totalPortfolios portfolios.");
    }

}
