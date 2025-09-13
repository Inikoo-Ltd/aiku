<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 10 Sept 2025 12:48:19 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */





/** @noinspection DuplicatedCode */

namespace App\Actions\Maintenance\Masters;

use App\Models\Masters\MasterAsset;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;
use Symfony\Component\Console\Helper\ProgressBar;

class RepairMasterProductsMainImageSubScope
{
    use AsAction;


    public function handle(MasterAsset $masterProduct): void
    {

        DB::table('model_has_media')
            ->where('media_id', $masterProduct->image_id)
            ->where('model_type', 'MasterAsset')
            ->where('model_id', $masterProduct->id)->update(['sub_scope' => 'main']);


    }

    public function getCommandSignature(): string
    {
        return 'master_products:repair_main_image_sub_scope';
    }

    public function asCommand(Command $command): int
    {
        $total = DB::table('master_assets')->count();
        $command->info("Repairing main image sub_scope for $total master products...");
        $start = microtime(true);
        $processed = 0;

        $bar = new ProgressBar($command->getOutput(), $total);
        $bar->setFormat('verbose');
        $bar->start();

        DB::table('master_assets')
            ->select('id')
            ->orderBy('id')
            ->chunkById(1000, function ($masterProductsRows) use (&$processed, $bar) {
                foreach ($masterProductsRows as $row) {
                    $masterProduct = MasterAsset::find($row->id);
                    if ($masterProduct) {
                        $this->handle($masterProduct);
                    }
                    $processed++;
                    $bar->advance();
                }
            }, 'id');

        $bar->finish();
        $command->newLine(2);
        $duration = microtime(true) - $start;
        $command->info("Done. Processed $processed/$total master products in ".gmdate('H:i:s', (int) $duration).".");

        return 0;
    }

}
