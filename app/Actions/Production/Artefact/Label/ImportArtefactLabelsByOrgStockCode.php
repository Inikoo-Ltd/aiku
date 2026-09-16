<?php

/*
 * Author: Vika Aqordi <aqordeon@gmail.com>
 * Created: Tue, 15 Sep 2026, Bali, Indonesia
 * Copyright (c) 2026, Inikoo LTD
 */

namespace App\Actions\Production\Artefact\Label;

use App\Models\Production\Artefact;
use App\Models\Production\Production;
use App\Models\SysAdmin\Organisation;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\File;
use Lorisleiva\Actions\Concerns\AsAction;
use SplFileInfo;
use Throwable;

class ImportArtefactLabelsByOrgStockCode
{
    use AsAction;

    public string $commandSignature = 'artefact-labels:import-by-org-stock {organisation} {production} {folder} {--dry-run}';

    /**
     * @return array{imported: array<int, string>, skipped: array<int, string>, unmatched: array<int, string>, failed: array<int, string>}
     */
    public function handle(Production $production, string $folder, bool $isDryRun = false): array
    {
        $artefactsByOrgStockCode = Artefact::query()
            ->where('production_id', $production->id)
            ->whereNotNull('org_stock_id')
            ->with('orgStock:id,code')
            ->get()
            ->filter(fn (Artefact $artefact) => $artefact->orgStock)
            ->keyBy(fn (Artefact $artefact) => strtoupper($artefact->orgStock->code));

        $pdfFiles = collect(File::allFiles($folder))
            ->filter(fn (SplFileInfo $file) => strtolower($file->getExtension()) === 'pdf')
            ->sortBy(fn (SplFileInfo $file) => $file->getPathname());

        $result  = ['imported' => [], 'skipped' => [], 'unmatched' => [], 'failed' => []];
        $matches = [];

        foreach ($pdfFiles as $pdfFile) {
            $filename = pathinfo($pdfFile->getFilename(), PATHINFO_FILENAME);
            $artefact = $this->findArtefactForFilename($artefactsByOrgStockCode, $filename);

            if (!$artefact) {
                $result['unmatched'][] = $pdfFile->getPathname();
                continue;
            }

            $matches[] = ['artefact' => $artefact, 'filename' => $filename, 'file' => $pdfFile];
        }

        $pathnamesKeepingFilename = collect($matches)
            ->groupBy(fn (array $match) => $match['artefact']->id.'|'.strtoupper($match['filename']))
            ->map(fn (Collection $group) => $group
                ->sortBy(fn (array $match) => [substr_count($match['file']->getPathname(), '/'), $match['file']->getPathname()])
                ->first()['file']
                ->getPathname())
            ->flip();

        foreach ($matches as $match) {
            $artefact  = $match['artefact'];
            $pdfFile   = $match['file'];
            $labelName = $match['filename'];

            if (!$pathnamesKeepingFilename->has($pdfFile->getPathname())) {
                $labelName = $this->labelNameWithSubfolder($labelName, $pdfFile, $folder);
            }

            $summary = "{$artefact->orgStock->code}: $labelName";

            if ($artefact->labels()->where('name', $labelName)->exists()) {
                $result['skipped'][] = $summary;
                continue;
            }

            if ($isDryRun) {
                $result['imported'][] = $summary;
                continue;
            }

            try {
                ImportArtefactLabelsFromFolder::make()->storeLabel($artefact, $labelName, $pdfFile->getPathname());
                $result['imported'][] = $summary;
            } catch (Throwable $exception) {
                $result['failed'][] = "$summary ({$exception->getMessage()})";
            }
        }

        return $result;
    }

    private function labelNameWithSubfolder(string $filename, SplFileInfo $pdfFile, string $folder): string
    {
        $subfolder = trim(substr($pdfFile->getPath(), strlen(rtrim($folder, '/'))), '/');

        return $subfolder === '' ? $filename : "$filename ($subfolder)";
    }

    /**
     * @param  Collection<string, Artefact>  $artefactsByOrgStockCode
     */
    private function findArtefactForFilename(Collection $artefactsByOrgStockCode, string $filename): ?Artefact
    {
        $segments = preg_split('/[\s_\-()]+/', strtoupper(trim($filename)), -1, PREG_SPLIT_NO_EMPTY);

        for ($segmentCount = count($segments); $segmentCount > 0; $segmentCount--) {
            $candidateCode        = implode('-', array_slice($segments, 0, $segmentCount));
            $candidateCodeVariant = preg_replace('/(?<=\d)[A-Z]$/', '', $candidateCode);

            foreach ([$candidateCode, $candidateCodeVariant] as $code) {
                if ($artefactsByOrgStockCode->has($code)) {
                    return $artefactsByOrgStockCode->get($code);
                }
            }
        }

        return null;
    }

    public function asCommand(Command $command): int
    {
        $organisation = Organisation::where('slug', $command->argument('organisation'))->first();

        if (!$organisation) {
            $command->error("Organisation {$command->argument('organisation')} not found");

            return 1;
        }

        $production = Production::where('organisation_id', $organisation->id)
            ->where('slug', $command->argument('production'))
            ->first();

        if (!$production) {
            $command->error("Production {$command->argument('production')} not found in $organisation->slug");

            return 1;
        }

        $folder = $command->argument('folder');
        $folder = str_starts_with($folder, '/') ? $folder : base_path($folder);

        if (!is_dir($folder)) {
            $command->error("Folder $folder not found");

            return 1;
        }

        $isDryRun = (bool) $command->option('dry-run');
        $result   = $this->handle($production, $folder, $isDryRun);

        foreach ($result['imported'] as $imported) {
            $command->line($imported);
        }

        foreach ($result['unmatched'] as $unmatched) {
            $command->warn("No org stock match: $unmatched");
        }

        foreach ($result['failed'] as $failed) {
            $command->error("Failed: $failed");
        }

        $command->info(
            ($isDryRun ? '[dry run] ' : '')
            .count($result['imported']).' imported, '
            .count($result['skipped']).' already exist, '
            .count($result['unmatched']).' unmatched, '
            .count($result['failed']).' failed'
        );

        return 0;
    }
}
