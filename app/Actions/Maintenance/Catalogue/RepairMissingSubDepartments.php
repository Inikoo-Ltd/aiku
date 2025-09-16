<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 15 Sept 2025 16:32:44 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Maintenance\Catalogue;

use App\Actions\Catalogue\ProductCategory\UpdateProductCategory;
use App\Actions\Traits\WithActionUpdate;
use App\Enums\Catalogue\ProductCategory\ProductCategoryTypeEnum;
use App\Models\Catalogue\ProductCategory;
use Illuminate\Console\Command;
use Lorisleiva\Actions\Concerns\AsAction;

class RepairMissingSubDepartments
{
    use AsAction;
    use WithActionUpdate;

    /**
     * @throws \Throwable
     */
    public function handle(ProductCategory $productCategory, array $modelData): void
    {
        $this->update($productCategory, [
            'sub_department_id' => $modelData['sub_department_id']
        ]);
    }

    public function getCommandSignature(): string
    {
        return 'repair:missing_sub_departments {file? : CSV file path (optional)}';
    }

    /**
     * @throws \Throwable
     */
    public function asCommand(Command $command): int
    {
        $filePath = $command->argument('file');
 
        if (!$filePath) {
            $filePath = $this->findLatestCsvFile($command);
            if (!$filePath) {
                $command->error('No CSV file specified and no CSV files found in storage.');
                $command->info('Usage: php artisan repair:missing_sub_departments [file_path]');
                return 1;
            }
        }

        // Check if file exists
        if (!file_exists($filePath)) {
            $command->error("File not found: {$filePath}");
            return 1;
        }

        $command->info("Reading CSV file: {$filePath}");
        
        try {
            $datas = $this->readCsvFile($filePath);
            foreach($datas as $data) {
                $productCategory = ProductCategory::find($data['id']);
                if($productCategory) {
                    $this->handle($productCategory, $data);
                }
            }
        } catch (\Exception $e) {
            $command->error("Error reading CSV file: " . $e->getMessage());
            return 1;
        }

        return 0;
    }

    private function findLatestCsvFile(Command $command): ?string
    {
        $storageDir = storage_path('app');
        $pattern = $storageDir . '/product_categories_sub_departments_*.csv';
        $files = glob($pattern);
        
        if (empty($files)) {
            return null;
        }

        usort($files, function($a, $b) {
            return filemtime($b) - filemtime($a);
        });

        $latestFile = $files[0];
        $command->info("Found latest CSV file: " . basename($latestFile));
        
        return $latestFile;
    }

    private function readCsvFile(string $filePath): array
    {
        $data = [];
        $handle = fopen($filePath, 'r');
        
        if (!$handle) {
            throw new \Exception("Unable to open file: {$filePath}");
        }

        // Read header
        $header = fgetcsv($handle);
        
        // Read data rows
        while (($row = fgetcsv($handle)) !== false) {
            if ($header) {
                // Create associative array with header as keys
                $data[] = array_combine($header, $row);
            } else {
                // If no header, use numeric indices
                $data[] = $row;
            }
        }
        
        fclose($handle);
        return $data;
    }
}