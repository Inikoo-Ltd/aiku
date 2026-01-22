<?php

/*
 * author Louis Perez
 * created on 22-01-2026-13h-45m
 * github: https://github.com/louis-perez
 * copyright 2026
*/

namespace App\Actions\Maintenance\Catalogue;

use App\Actions\Traits\WithActionUpdate;
use App\Enums\Catalogue\Product\ProductStateEnum;
use App\Enums\Web\Webpage\WebpageStateEnum;
use App\Models\Catalogue\Product;
use Illuminate\Console\Command;

class RepairProductsHasLiveWebpage
{
    use WithActionUpdate;

    public string $commandSignature = 'product:repair_has_live_webpage';

    public function asCommand(Command $command): void
    {
        $command->info('Repairing Products has_live_webpage');

        $query = Product::where('state', '!=', ProductStateEnum::DISCONTINUED->value);

        $total = (clone $query)->count();

        $bar = $command->getOutput()->createProgressBar($total);
        $bar->start();

        $query->chunk(200, function ($products) use ($bar) {
            foreach ($products as $product) {
                $product->update([
                    'has_live_webpage' => $product->webpage()
                        ->where('state', WebpageStateEnum::LIVE)
                        ->exists(),
                ]);

                $bar->advance();
            }
        });

        $bar->finish();
        $command->newLine();
    }

}
