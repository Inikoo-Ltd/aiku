<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 10 Sept 2025 12:48:19 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */





/** @noinspection DuplicatedCode */

namespace App\Actions\Maintenance\Catalogue;

use App\Models\Catalogue\Product;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;
use Symfony\Component\Console\Helper\ProgressBar;

class RepairProductsMainImageSubScope
{
    use AsAction;


    public function handle(Product $product): void
    {

        DB::table('model_has_media')
            ->where('media_id', $product->image_id)
            ->where('model_type', 'Product')
            ->where('model_id', $product->id)->update(['sub_scope' => 'main']);


    }

    public function getCommandSignature(): string
    {
        return 'products:repair_main_image_sub_scope';
    }

    public function asCommand(Command $command): int
    {
        $total = DB::table('products')->count();
        $command->info("Repairing main image sub_scope for $total products...");
        $start = microtime(true);
        $processed = 0;

        $bar = new ProgressBar($command->getOutput(), $total);
        $bar->setFormat('verbose');
        $bar->start();

        DB::table('products')
            ->select('id')
            ->orderBy('id')
            ->chunkById(1000, function ($productsRows) use (&$processed, $bar) {
                foreach ($productsRows as $row) {
                    $product = Product::find($row->id);
                    if ($product) {
                        $this->handle($product);
                    }
                    $processed++;
                    $bar->advance();
                }
            }, 'id');

        $bar->finish();
        $command->newLine(2);
        $duration = microtime(true) - $start;
        $command->info("Done. Processed $processed/$total products in ".gmdate('H:i:s', (int) $duration).".");

        return 0;
    }

}
