<?php

namespace App\Actions\Goods\TradeUnit;

use App\Actions\Helpers\Brand\AttachBrandToModel;
use App\Models\Goods\TradeUnit;
use App\Models\Helpers\Brand;
use Illuminate\Console\Command;
use Lorisleiva\Actions\Concerns\AsAction;
use PhpOffice\PhpSpreadsheet\IOFactory;

class RepairTradeUnitDescriptionAndBrandFromXlsx
{
    use AsAction;

    public string $commandSignature = 'trade_units:repair_description_and_brand_from_xlsx {--path= : Path to xlsx file}';

    public function handle(string $filePath, ?Command $command = null): void
    {
        $reader = IOFactory::createReader('Xlsx');
        $reader->setReadDataOnly(true);
        $spreadsheet = $reader->load($filePath);
        $rows        = array_slice($spreadsheet->getActiveSheet()->toArray(), 1);

        $skippedDescription = 0;
        $skippedBrand       = 0;
        $updatedDescription = 0;
        $updatedBrand       = 0;
        $notFound           = 0;
        $brandNotFound      = 0;

        $bar = $command?->getOutput()->createProgressBar(count($rows));
        $bar?->start();

        foreach ($rows as $row) {
            $code        = trim((string) ($row[1] ?? ''));
            $description = trim((string) ($row[3] ?? ''));
            $brandName   = trim((string) ($row[9] ?? ''));

            if ($code === '') {
                $bar?->advance();
                continue;
            }

            $tradeUnit = TradeUnit::where('code', $code)->first();

            if (!$tradeUnit) {
                $notFound++;
                $bar?->advance();
                continue;
            }

            if ($description !== '' && $tradeUnit->description === null) {
                $tradeUnit->update(['description' => $description]);
                $updatedDescription++;
            } else {
                $skippedDescription++;
            }

            if ($brandName !== '' && $tradeUnit->brands()->count() === 0) {
                $brand = Brand::where('name', $brandName)->first();

                if ($brand) {
                    AttachBrandToModel::make()->action($tradeUnit, ['brand_id' => $brand->id]);
                    $updatedBrand++;
                } else {
                    $command?->line('Brand '.$brandName.' not found');
                    $brandNotFound++;
                }
            } else {
                $skippedBrand++;
            }

            $bar?->advance();
        }

        $bar?->finish();

        if ($command) {
            $command->newLine(2);
            $command->info("Description updated: $updatedDescription | skipped (already set): $skippedDescription");
            $command->info("Brand attached: $updatedBrand | skipped (already set): $skippedBrand");
            $command->warn("Trade units not found: $notFound");
            $command->warn("Brands not found in DB: $brandNotFound");
        }
    }

    public function asCommand(Command $command): void
    {
        $path = $command->option('path');

        if (!file_exists($path)) {
            $command->error("File not found: $path");
            return;
        }

        $this->handle($path, $command);
    }
}
