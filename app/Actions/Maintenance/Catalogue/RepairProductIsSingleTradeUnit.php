<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 09 Dec 2025 16:04:59 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Maintenance\Catalogue;

use App\Actions\OrgAction;
use App\Actions\Traits\ModelHydrateSingleTradeUnits;
use App\Models\Catalogue\Product;
use Exception;
use Illuminate\Console\Command;
use Lorisleiva\Actions\Concerns\AsAction;

class RepairProductIsSingleTradeUnit extends OrgAction
{
    use AsAction;


    /**
     * @throws \Throwable
     */
    public function handle(Product $product): Product
    {
        ModelHydrateSingleTradeUnits::run($product);
        return $product;
    }

    public function getCommandSignature(): string
    {
        return 'repair:product_is_single_trade_unit';
    }

    /**
     * @throws \Throwable
     */
    public function asCommand(Command $command): int
    {



        $command->info('Fix product is single trade unit');

        $chunkSize = 100;
        $count     = 0;

        $totalCount = Product::count();

        $bar = $command->getOutput()->createProgressBar($totalCount);
        $bar->setFormat(' %current%/%max% [%bar%] %percent:3s%% %elapsed:6s%/%estimated:-6s% %memory:6s%');
        $bar->start();


        Product::chunk(
            $chunkSize,
            function ($products) use (&$count, $bar, $command) {
                foreach ($products as $product) {
                    try {
                        $this->handle($product);
                    } catch (Exception $e) {
                        $command->error("Error processing product $product->id: {$e->getMessage()}");
                    }
                    $count++;
                    $bar->advance();
                }
            }
        );

        $bar->finish();
        $command->newLine();

        return 0;
    }
}
