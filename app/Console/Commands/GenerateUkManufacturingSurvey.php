<?php

namespace App\Console\Commands;

use App\Exports\Reports\UkManufacturingSurveyExport;
use App\Models\SysAdmin\Organisation;
use Illuminate\Console\Command;
use Maatwebsite\Excel\Facades\Excel;

class GenerateUkManufacturingSurvey extends Command
{
    protected $signature = 'report:uk-manufacturing-survey
                            {--organisation=aroma : Organisation slug}
                            {--start-date= : Start date (Y-m-d format, defaults to start of previous year)}
                            {--end-date= : End date (Y-m-d format, defaults to end of previous year)}
                            {--output= : Output file path (optional, defaults to storage/app)}';

    protected $description = 'Generate UK Manufacturing Survey Excel report for an organisation, grouped by 6-digit tariff code';

    public function handle(): int
    {
        $slug      = $this->option('organisation');
        $startDate = $this->option('start-date') ?? now()->subYear()->startOfYear()->format('Y-m-d');
        $endDate   = $this->option('end-date') ?? now()->subYear()->endOfYear()->format('Y-m-d');

        $organisation = Organisation::where('slug', $slug)->first();

        if (!$organisation) {
            $this->error("Organisation '{$slug}' not found.");

            return Command::FAILURE;
        }

        $relativeFilename = "uk-manufacturing-survey-{$slug}-{$startDate}-to-{$endDate}.xlsx";
        $outputPath       = $this->option('output') ?? storage_path("app/{$relativeFilename}");

        $this->info("Generating UK Manufacturing Survey for {$organisation->name}");
        $this->info("Period: {$startDate} to {$endDate}");

        Excel::store(
            new UkManufacturingSurveyExport($organisation->id, $startDate, $endDate),
            $relativeFilename,
            'local',
            \Maatwebsite\Excel\Excel::XLSX
        );

        $storedPath = storage_path("app/{$relativeFilename}");

        if ($outputPath !== $storedPath) {
            rename($storedPath, $outputPath);
            $storedPath = $outputPath;
        }

        $this->info("✓ Report saved to: {$storedPath}");

        return Command::SUCCESS;
    }
}
