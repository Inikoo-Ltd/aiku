<?php
/*
 * author Arya Permana - Kirin
 * created on 02-07-2025-18h-07m
 * github: https://github.com/KirinZero0
 * copyright 2025
*/

namespace App\Actions\Maintenance\Dropshipping;

use App\Actions\Traits\WithActionUpdate;
use App\Models\Dropshipping\Portfolio;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class RepairSellingPriceInProductPortfolios
{
    use WithActionUpdate;


    public function handle(Portfolio $portfolio): void
    {
        if ($portfolio->item_type === 'Product') {
            $rrp = DB::table('products')
                ->where('id', $portfolio->item_id)
                ->value('rrp');

            if ($rrp !== null) {
                DB::table('portfolios')
                    ->where('id', $portfolio->id)
                    ->update(['selling_price' => $rrp]);
            }
        }
    }
    public string $commandSignature = 'repair:portfolio_selling_price {portfolio?}';

    public function asCommand(Command $command): void
    {
        if ($command->argument('portfolio')) {
            $portfolio = Portfolio::find($command->argument('portfolio'));
            $this->handle($portfolio);
            
        } else {
            $count = Portfolio::where('item_type', 'Product')->count();

            $bar = $command->getOutput()->createProgressBar($count);
            $bar->setFormat('debug');
            $bar->start();

            Portfolio::orderBy('id')->where('item_type', 'Product')
                ->chunk(100, function (Collection $models) use ($bar) {
                    foreach ($models as $model) {
                        $this->handle($model);
                        $bar->advance();
                    }
                });
        }
    }

}
