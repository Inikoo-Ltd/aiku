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
use Lorisleiva\Actions\Concerns\AsAction;

class RepairPortfoliosSKUBarcodes
{
    use asAction;

    public function handle(Portfolio $portfolio): Portfolio
    {

            /** @var Product|StoredItem $item */
            $item=$portfolio->item;


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

    public function asCommand(): void
    {
        Portfolio::chunkById(100, function ($portfolios) {
            foreach ($portfolios as $portfolio) {
                $this->handle($portfolio);
            }
        });
    }

}
