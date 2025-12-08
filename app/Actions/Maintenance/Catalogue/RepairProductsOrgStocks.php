<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 24 Sept 2025 18:21:40 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Maintenance\Catalogue;

use App\Actions\OrgAction;
use App\Models\Catalogue\Product;
use Illuminate\Console\Command;
use Lorisleiva\Actions\Concerns\AsAction;

class RepairProductsOrgStocks extends OrgAction
{
    use AsAction;


    public function handle(Product $product): Product
    {
        $tradeUnits = $product->tradeUnits;

        print "$product->slug \n";
        $stocks = [];
        foreach ($tradeUnits as $tradeUnit) {
            foreach ($tradeUnit->orgStocks as $orgStock) {
                if ($orgStock->organisation_id == $product->organisation_id) {
                    // dd( $orgStock->pivot,);


                    if (array_key_exists($orgStock->id, $stocks)) {
                        $stocks[$orgStock->id] = [
                            'quantity' => ($tradeUnit->pivot->quantity / $orgStock->pivot->quantity) + $stocks[$orgStock->id]['quantity'],
                        ];
                    } else {
                        $stocks[$orgStock->id] = [
                            'quantity' => $tradeUnit->pivot->quantity /
                                $orgStock->pivot->quantity,
                        ];
                    }
                }
            }
        }

        $product->orgStocks()->sync($stocks);


        return $product;
    }

    public function getCommandSignature(): string
    {
        return 'repair:products_org_stocks {product?}';
    }

    public function asCommand(Command $command): int
    {
        if ($command->argument('product')) {
            $product = Product::where('slug', $command->argument('product'))->firstOrFail();
            $this->handle($product);

            return 0;
        }


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
                    $this->handle($product);
                    $count++;
                    $bar->advance();
                }
            }
        );

        $bar->finish();
        $command->newLine();
        $command->info("$count products processed");

        return 0;
    }
}
