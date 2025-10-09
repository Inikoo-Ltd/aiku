<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 24 Sept 2025 18:21:40 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Maintenance\Catalogue;

use App\Actions\OrgAction;
use App\Models\Catalogue\Product;
use App\Models\Inventory\OrgStock;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
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

            //  dd($tradeUnit->id);


            //           $orgStocksData= DB::table('model_has_trade_units')
            //               ->leftjoin('org_stocks', 'org_stocks.id', '=', 'model_has_trade_units.model_id')
            //               ->select('model_has_trade_units.id')
            //                ->where('model_has_trade_units.model_type', 'OrgStock')
            //                ->where('model_has_trade_units.trade_unit_id', $tradeUnit->id)
            //                ->where('org_stocks.organisation_id', $product->organisation_id)->get();
            //
            //           foreach ($orgStocksData as $orgStockData) {
            //               dd($orgStockData
            //               );
            //           }



            foreach ($tradeUnit->orgStocks as $orgStock) {

                if ($orgStock->organisation_id == $product->organisation_id) {

                    // dd( $orgStock->pivot,);


                    if (array_key_exists($orgStock->id, $stocks)) {
                        $stocks[$orgStock->id] = [
                            'quantity' => (
                                $tradeUnit->pivot->quantity /
                                    $orgStock->pivot->quantity
                            ) + $stocks[$orgStock->id]['quantity'],
                        ];
                    } else {
                        $_org_stock = OrgStock::find($orgStock->id);
                        //   dd($_org_stock->code);
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
        return 'repair:products_org_stocks';
    }

    public function asCommand(Command $command): int
    {
        $product = Product::find(200604);
        $this->handle($product);
        exit;

        $chunkSize = 100;
        $count = 0;
        $matchedCount = 0;

        $totalCount = Product::count();

        $bar = $command->getOutput()->createProgressBar($totalCount);
        $bar->setFormat(' %current%/%max% [%bar%] %percent:3s%% %elapsed:6s%/%estimated:-6s% %memory:6s%');
        $bar->start();


        Product::chunk(
            $chunkSize,
            function ($products) use (&$count, &$matchedCount, $bar, $command) {
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
