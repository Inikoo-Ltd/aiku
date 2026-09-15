<?php

/*
 * Author: Vika Aqordi <aqordeon@gmail.com>
 * Created: Mon, 14 Sep 2026, Bali, Indonesia
 * Copyright (c) 2026, Inikoo LTD
 */

namespace App\Actions\Production\Artefact\Label;

use App\Models\Production\Artefact;
use App\Models\Production\Production;
use Illuminate\Console\Command;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Lorisleiva\Actions\Concerns\AsAction;
use SplFileInfo;

class ImportArtefactLabelsFromFolder
{
    use AsAction;

    private const SHEET_ARTWORK_LAYOUT = [
        'orientation'      => 'portrait',
        'columns'          => 1,
        'rows'             => 1,
        'page_margin'      => 0,
        'gap'              => 0,
        'cut_guides'       => false,
        'canvas_rotation'  => 0,
        'is_sheet_artwork' => true,
        'fields'           => [],
    ];

    public string $commandSignature = 'artefact-labels:import {production} {family} {folder}';

    /**
     * @return array<int, string>
     */
    public function handle(Production $production, string $familyCode, string $folder): array
    {
        $artefacts = Artefact::query()
            ->where('production_id', $production->id)
            ->whereHas('artefactFamily', fn ($query) => $query->whereRaw('lower(code) = ?', [strtolower($familyCode)]))
            ->get()
            ->keyBy(fn (Artefact $artefact) => strtoupper($artefact->code));

        $pdfFiles = collect(File::files($folder))
            ->filter(fn (SplFileInfo $file) => strtolower($file->getExtension()) === 'pdf')
            ->sortBy(fn (SplFileInfo $file) => $file->getFilename());

        $importedLabels = [];

        foreach ($pdfFiles as $pdfFile) {
            $labelName = pathinfo($pdfFile->getFilename(), PATHINFO_FILENAME);
            $artefact  = $artefacts->get(strtoupper(preg_split('/[\s_]+/', $labelName)[0]));

            if (!$artefact || $artefact->labels()->where('name', $labelName)->exists()) {
                continue;
            }

            $this->storeLabel($artefact, $labelName, $pdfFile->getPathname());

            $importedLabels[] = "$artefact->code: $labelName";
        }

        return $importedLabels;
    }

    private function storeLabel(Artefact $artefact, string $labelName, string $pdfPath): void
    {
        $workingCopyPath = sys_get_temp_dir().'/'.Str::uuid().'.pdf';
        copy($pdfPath, $workingCopyPath);

        try {
            StoreArtefactLabel::make()->action($artefact, array_merge(self::SHEET_ARTWORK_LAYOUT, [
                'name'    => $labelName,
                'artwork' => new UploadedFile($workingCopyPath, basename($pdfPath), 'application/pdf', null, true),
            ]));
        } finally {
            if (is_file($workingCopyPath)) {
                unlink($workingCopyPath);
            }
        }
    }

    public function asCommand(Command $command): int
    {
        $production = Production::where('slug', $command->argument('production'))->first();

        if (!$production) {
            $command->error("Production {$command->argument('production')} not found");

            return 1;
        }

        $folder = $command->argument('folder');
        $folder = str_starts_with($folder, '/') ? $folder : base_path($folder);

        if (!is_dir($folder)) {
            $command->error("Folder $folder not found");

            return 1;
        }

        $importedLabels = $this->handle($production, $command->argument('family'), $folder);

        foreach ($importedLabels as $importedLabel) {
            $command->line($importedLabel);
        }

        $command->info(count($importedLabels).' labels imported');

        return 0;
    }
}
