<?php

namespace App\Actions\Inventory\Reports;

use App\Actions\OrgAction;
use App\Models\SysAdmin\Organisation;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Storage;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use ZipArchive;

class DownloadPackagingReport extends OrgAction
{
    use AsAction;

    public function handle(string $startDate, string $endDate, string $organisationSlug): string
    {
        $timestamp = now()->format('YmdHis');
        $outputDir = "packaging-reports-{$timestamp}";

        Artisan::call('packaging:report', [
            '--start-date' => $startDate,
            '--end-date' => $endDate,
            '--organisation' => $organisationSlug,
            '--output-dir' => $outputDir,
        ]);

        return $outputDir;
    }

    public function asController(Organisation $organisation, ActionRequest $request): BinaryFileResponse
    {
        $this->initialisation($organisation, $request);

        $startDate = $request->get('start_date', now()->startOfMonth()->format('Y-m-d'));
        $endDate = $request->get('end_date', now()->endOfMonth()->format('Y-m-d'));

        $outputDir = $this->handle($startDate, $endDate, $organisation->slug);

        $zipPath = storage_path("app/{$outputDir}.zip");
        $zip = new ZipArchive();

        if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) === true) {
            $files = [
                'buy_from_uk.csv',
                'imports.csv',
                'sales_uk.csv',
                'exports.csv'
            ];

            foreach ($files as $file) {
                $filePath = storage_path("app/{$outputDir}/{$file}");
                if (file_exists($filePath)) {
                    $zip->addFile($filePath, $file);
                }
            }

            $zip->close();
        }

        $response = response()->download($zipPath, "packaging-reports-{$startDate}-to-{$endDate}.zip")->deleteFileAfterSend(true);

        Storage::deleteDirectory($outputDir);

        return $response;
    }
}
