<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 15 Sept 2025 14:20:38 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Maintenance\Catalogue;

use App\Actions\Maintenance\Masters\AddMissingMasterAssets;
use App\Actions\Masters\MasterAsset\MatchAssetsToMaster;
use App\Actions\Traits\WithActionUpdate;
use App\Enums\Catalogue\Product\ProductStateEnum;
use App\Models\Catalogue\Product;
use App\Models\Catalogue\ProductCategory;
use App\Models\Catalogue\Shop;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;

class CVSSubDepartment
{
    use WithActionUpdate;

    /**
     * @throws \Throwable
     */
    public function handle(ProductCategory $productCategory): void
    {
        // Your existing logic here
    }

    public string $commandSignature = 'cvs:sub_departments {--export-csv : Export data to CSV file}';

    public function asCommand(Command $command): void
    {
        if ($command->option('export-csv')) {
            $this->exportToCSV($command);
            return;
        }

        $count = ProductCategory::whereNotNull('sub_department_id')->count();

        $bar = $command->getOutput()->createProgressBar($count);
        $bar->setFormat('debug');
        $bar->start();

        ProductCategory::whereNotNull('sub_department_id')->orderBy('id')
            ->chunk(100, function (Collection $models) use ($bar, $command) {
                foreach ($models as $model) {
                    $this->handle($model, $command);
                    $bar->advance();
                }
            });
        
        $bar->finish();
    }

    private function exportToCSV(Command $command): void
    {
        $command->info('Exporting ProductCategory data to CSV...');
        
        $filename = 'product_categories_sub_departments_' . date('Y-m-d_H-i-s') . '.csv';
        $filepath = storage_path('app/' . $filename);
        
        // Open file for writing
        $handle = fopen($filepath, 'w');
        
        if (!$handle) {
            $command->error('Could not create CSV file');
            return;
        }

        // Write CSV header
        fputcsv($handle, ['id', 'sub_department_id']);

        $count = ProductCategory::whereNotNull('sub_department_id')->count();
        $bar = $command->getOutput()->createProgressBar($count);
        $bar->setFormat('debug');
        $bar->start();

        // Write data in chunks to handle large datasets efficiently
        ProductCategory::whereNotNull('sub_department_id')
            ->orderBy('id')
            ->select(['id', 'sub_department_id'])
            ->chunk(1000, function (Collection $models) use ($handle, $bar) {
                foreach ($models as $model) {
                    fputcsv($handle, [
                        $model->id,
                        $model->sub_department_id
                    ]);
                    $bar->advance();
                }
            });

        $bar->finish();
        fclose($handle);

        $command->info("\nCSV file created successfully: {$filename}");
        $command->info("Location: {$filepath}");
        $command->info("Total records exported: {$count}");
    }
}