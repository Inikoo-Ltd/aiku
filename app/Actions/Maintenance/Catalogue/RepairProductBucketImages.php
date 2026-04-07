<?php

/*
 * author Louis Perez
 * created on 07-04-2026-09h-59m
 * github: https://github.com/louis-perez
 * copyright 2026
*/

namespace App\Actions\Maintenance\Catalogue;

use App\Actions\Traits\WithActionUpdate;
use App\Models\Catalogue\Product;
use App\Models\Helpers\Language;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;

class RepairProductBucketImages
{
    use WithActionUpdate;


    public string $commandSignature = 'products:repair_bucket_images';

    public function handle(Product $product)
    {
        $hasImages = count($product->images) > 0 || !empty($product->video_url);

        $product->updateQuietly([
            'bucket_images' => $hasImages
        ]);
    }

    public function asCommand(Command $command): void
    {
        $command->info('Repairing Products bucket_images');

        $query  = Product::query();
        
        $totalCount     = $query->clone()->count();

        $progressBar    = $command->getOutput()->createProgressBar($totalCount);
        $progressBar->setFormat(' %current%/%max% [%bar%] %percent:3s%% %elapsed:6s%/%estimated:-6s% %memory:6s%');
        $progressBar->start();

        $query
            ->orderBy('id')
            ->chunkById(1000, function ($products) use (&$progressBar) {
                foreach ($products as $product) {
                    $this->handle($product);

                    $progressBar->advance();
                }
            });
                    
        $progressBar->finish();
        $command->newLine();
    }

}
