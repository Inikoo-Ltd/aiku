<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 24 Nov 2025 22:38:18 Central Indonesia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Maintenance\Catalogue;

use App\Actions\Catalogue\Product\SyncProductOrgStocksFromTradeUnits;
use App\Models\Catalogue\Shop;
use Illuminate\Console\Command;
use Lorisleiva\Actions\Concerns\AsAction;

class RepairProductOrgStocks
{
    use asAction;

    /**
     * @throws \Throwable
     */
    public function handle(Shop $shop, Command $command = null): void
    {
        $shop->products()
            ->orderBy('id')
            ->chunkById(500, function ($products) use ($command) {
                foreach ($products as $product) {
                    foreach ($product->orgStocks as $orgStock) {
                        if ($orgStock->organisation_id != $product->organisation_id) {
                            $command->info("Error found org stock $orgStock->id ($orgStock->organisation_id) for product $product->id $product->organisation_id");
                            SyncProductOrgStocksFromTradeUnits::run($product);

                        }
                    }
                }
            });
    }



    public function getCommandSignature(): string
    {
        return 'shop:fix_product_org_stocks {shop}';
    }

    /**
     * @throws \Throwable
     */
    public function asCommand(Command $command): int
    {

        $shop = Shop::where('slug', $command->argument('shop'))->firstOrFail();
        $command->info("Fixing product org stocks fot $shop->name");


        $this->handle($shop, $command);

        return 0;
    }


}
