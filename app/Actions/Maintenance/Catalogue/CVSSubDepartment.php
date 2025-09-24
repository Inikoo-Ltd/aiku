<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 15 Sept 2025 14:20:38 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Maintenance\Catalogue;

use App\Actions\Traits\WithActionUpdate;
use App\Models\Catalogue\ProductCategory;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;

class CVSSubDepartment
{
    use WithActionUpdate;



    public string $commandSignature = 'cvs:sub_departments {--export-csv : Export data to CSV file}';

    public function asCommand(Command $command): void
    {
        if ($command->option('export-csv')) {
            $this->exportToCSV($command);

        }


    }

    private function exportToCSV(Command $command): void
    {
        $command->info('Exporting ProductCategory data to CSV...');

        $filename = 'product_categories_sub_departments_' . date('Y-m-d_H-i-s') . '.csv';
        $filepath = storage_path('app/' . $filename);

        // Open a file for writing
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

        $command->info("\nCSV file created successfully: $filename");
        $command->info("Location: $filepath");
        $command->info("Total records exported: $count");
    }
}
