<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 04 Sep 2026 15:00:00 Central European Summer Time, Mijas, Spain
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Console\Commands;

use App\Actions\Production\Artefact\AttachRawMaterialToRecipeStep;
use App\Actions\Production\Artefact\StoreArtefact;
use App\Actions\Production\ManufactureTask\StoreManufactureTask;
use App\Actions\Production\RawMaterial\StoreRawMaterial;
use App\Enums\Production\ManufactureTask\ManufactureTaskOperativeRewardAllowanceTypeEnum;
use App\Enums\Production\ManufactureTask\ManufactureTaskOperativeRewardTermsEnum;
use App\Enums\Production\RawMaterial\RawMaterialTypeEnum;
use App\Models\Production\Artefact;
use App\Models\Production\ArtefactManufactureTask;
use App\Models\Production\ManufactureTask;
use App\Models\Production\Production;
use App\Models\Production\RawMaterial;
use Illuminate\Console\Command;
use Illuminate\Support\Str;
use Laravel\Nightwatch\Facades\Nightwatch;

class ImportProductionCostings extends Command
{
    protected $signature = 'manufacture:import-costings
                           {production : Production slug}
                           {dir : Folder holding import.json (review CSVs are written to <dir>/review)}
                           {--phase=materials : materials | artefacts | recipes}
                           {--write : Persist changes; without it the command only writes the review files}';

    protected $description = 'Import raw materials, artefacts and per-unit recipes extracted from the production costings workbook';

    private const STOP_WORDS = ['essential', 'oil', 'oils', 'base', 'kg', 'l', 'litre', 'fragrance', 'fragrace', 'flavour', 'flavouring', 'organic', 'refined', 'the', 'and', 'of', 'for', 'soap', 'powder', 'cold', 'pressed', 'no'];

    private Production $production;
    private bool $write = false;
    private string $reviewDir;
    /** @var array<int, RawMaterial|null> */
    private array $resolved = [];
    /** @var array<string, list<RawMaterial>> */
    private array $byNormalisedName = [];

    public function handle(): int
    {
        Nightwatch::dontSample();

        $production = Production::where('slug', $this->argument('production'))->first();
        if (!$production) {
            $this->error("Unknown production '{$this->argument('production')}'.");

            return Command::FAILURE;
        }
        $this->production = $production;

        $path = rtrim($this->argument('dir'), '/').'/import.json';
        if (!is_readable($path)) {
            $this->error("Cannot read '{$path}'.");

            return Command::FAILURE;
        }
        $import = json_decode(file_get_contents($path), true);
        if (!is_array($import) || !isset($import['materials'], $import['artefacts'])) {
            $this->error('import.json must contain "materials" and "artefacts".');

            return Command::FAILURE;
        }

        $this->resolved         = [];
        $this->byNormalisedName = [];
        $this->write            = (bool) $this->option('write');
        $this->reviewDir = rtrim($this->argument('dir'), '/').'/review';
        if (!is_dir($this->reviewDir)) {
            mkdir($this->reviewDir, 0755, true);
        }

        $this->indexRawMaterials();

        $counts = match ($this->option('phase')) {
            'materials' => $this->importMaterials($import['materials']),
            'artefacts' => $this->importArtefacts($import['artefacts']),
            'recipes'   => $this->importRecipes($import['artefacts'], $import['materials']),
            default     => null,
        };
        if ($counts === null) {
            $this->error('Phase must be materials, artefacts or recipes.');

            return Command::FAILURE;
        }

        $this->info(($this->write ? 'Written. ' : 'Dry run. ').collect($counts)->map(fn ($v, $k) => "$k: $v")->implode(', '));

        return Command::SUCCESS;
    }

    /** @param list<array{master_row:int,code:?string,name:string,cost:float,unit:string,cas:?string,inci:?string,section:string,family:?string,times_used:int,aiku_code:?string,pack_size:?float}> $materials */
    private function importMaterials(array $materials): array
    {
        $counts = ['exact' => 0, 'alias' => 0, 'auto' => 0, 'created' => 0, 'unused' => 0];
        $rows   = [];
        foreach ($materials as $material) {
            if ((int) $material['times_used'] === 0) {
                $counts['unused']++;
                continue;
            }
            [$rawMaterial, $how] = $this->resolveMaterial($material);
            if (!$rawMaterial) {
                $how = 'created';
                if ($this->write) {
                    $rawMaterial = $this->createRawMaterial($material);
                }
            }
            $counts[$how]++;
            $rows[] = [
                $material['master_row'],
                $material['section'],
                $material['name'],
                $material['cost'],
                $material['unit'],
                $material['times_used'],
                $how,
                $rawMaterial?->code,
                $rawMaterial?->description,
                $rawMaterial?->unit_cost,
            ];
        }
        $this->writeCsv('materials_review.csv', ['master_row', 'section', 'sheet_name', 'sheet_cost', 'unit', 'times_used', 'resolution', 'aiku_code', 'aiku_description', 'aiku_unit_cost'], $rows);

        return $counts;
    }

    /** @param list<array{code:string,name:string,create:bool,summary_row:int}> $artefacts */
    private function importArtefacts(array $artefacts): array
    {
        $counts = ['exists' => 0, 'created' => 0, 'invalid_code' => 0];
        $rows   = [];
        foreach ($artefacts as $artefact) {
            if (!$artefact['create']) {
                continue;
            }
            $code = $artefact['code'];
            if ($this->findArtefact($code)) {
                $counts['exists']++;
                $rows[] = [$code, $artefact['name'], 'exists'];
                continue;
            }
            if (!preg_match('/^[A-Za-z0-9._-]+$/', $code)) {
                $counts['invalid_code']++;
                $rows[] = [$code, $artefact['name'], 'invalid code'];
                continue;
            }
            $counts['created']++;
            $rows[] = [$code, $artefact['name'], 'created'];
            if ($this->write) {
                StoreArtefact::make()->action($this->production, [
                    'code'      => $code,
                    'name'      => Str::limit($artefact['name'], 255, ''),
                    'source_id' => 'costings:summary:'.$artefact['summary_row'],
                ]);
            }
        }
        $this->writeCsv('artefacts_review.csv', ['code', 'name', 'result'], $rows);

        return $counts;
    }

    /**
     * @param list<array{code:string,name:string,summary_row:int,cost_per_unit:?float,lines:list<array{master_row:int,label:?string,quantity_per_unit:float}>}> $artefacts
     * @param list<array{master_row:int,cost:float,aiku_code:?string,pack_size:?float}> $materials  pack_size = sheet units (kg or litre) per aiku unit of the aliased material
     */
    private function importRecipes(array $artefacts, array $materials): array
    {
        $masterCost = collect($materials)->keyBy('master_row')->map(fn ($m) => (float) $m['cost']);
        $materialByRow = collect($materials)->keyBy('master_row');
        $counts = ['imported' => 0, 'kept_existing' => 0, 'no_artefact' => 0, 'no_lines' => 0, 'unresolved_material' => 0];
        $rows   = [];
        $compare = [];
        foreach ($artefacts as $data) {
            $artefact = $this->findArtefact($data['code']);
            if (!$artefact) {
                $counts['no_artefact']++;
                $rows[] = [$data['code'], $data['name'], 'no artefact', 0, null, null, null, ''];
                continue;
            }
            if (empty($data['lines'])) {
                $counts['no_lines']++;
                $rows[] = [$data['code'], $data['name'], 'no lines in workbook', 0, null, null, null, ''];
                continue;
            }

            $resolvedLines = [];
            $unresolved    = [];
            $sheetMaterialCost = 0.0;
            foreach ($data['lines'] as $line) {
                $material = $materialByRow->get($line['master_row']);
                if (!$material) {
                    $unresolved[] = 'row '.$line['master_row'];
                    continue;
                }
                [$rawMaterial] = $this->resolveMaterial($material);
                $sheetMaterialCost += $line['quantity_per_unit'] * $masterCost->get($line['master_row'], 0);
                if (!$rawMaterial) {
                    $unresolved[] = $material['name'];
                    continue;
                }
                $packSize        = (float) ($material['pack_size'] ?? 1) ?: 1;
                $resolvedLines[] = ['raw_material' => $rawMaterial, 'quantity_per_unit' => round((float) $line['quantity_per_unit'] / $packSize, 6), 'label' => $line['label'] ?? $material['name']];
            }

            $existing = $this->existingRecipeLines($artefact);
            if ($existing->isNotEmpty()) {
                $counts['kept_existing']++;
                $aikuCost = $existing->sum(fn ($l) => $l->quantity_per_unit * $l->rawMaterial->unit_cost);
                $rows[]   = [$data['code'], $data['name'], 'existing recipe kept', $existing->count(), round($sheetMaterialCost, 4), round($aikuCost, 4), $data['cost_per_unit'], implode('; ', $unresolved)];
                $compare  = array_merge($compare, $this->compareLines($data['code'], $existing, $resolvedLines));
                continue;
            }

            if ($unresolved) {
                $counts['unresolved_material']++;
                $rows[] = [$data['code'], $data['name'], 'skipped: unresolved material', count($resolvedLines), round($sheetMaterialCost, 4), null, $data['cost_per_unit'], implode('; ', $unresolved)];
                continue;
            }

            $aikuCost = collect($resolvedLines)->sum(fn ($l) => $l['quantity_per_unit'] * $l['raw_material']->unit_cost);
            $counts['imported']++;
            $rows[] = [$data['code'], $data['name'], 'imported', count($resolvedLines), round($sheetMaterialCost, 4), round($aikuCost, 4), $data['cost_per_unit'], ''];
            if ($this->write) {
                $step = $this->recipeStep($artefact);
                foreach ($resolvedLines as $line) {
                    AttachRawMaterialToRecipeStep::make()->action($step, [
                        'raw_material_id'   => $line['raw_material']->id,
                        'quantity_per_unit' => $line['quantity_per_unit'],
                    ]);
                }
            }
        }
        $this->writeCsv('recipes_review.csv', ['code', 'name', 'result', 'lines', 'sheet_material_cost_per_unit', 'aiku_material_cost_per_unit', 'sheet_full_cost_per_unit', 'notes'], $rows);
        $this->writeCsv('recipes_compare_existing.csv', ['code', 'raw_material', 'aiku_quantity_per_unit', 'sheet_quantity_per_unit', 'note'], $compare);

        return $counts;
    }

    /** @return array{0: ?RawMaterial, 1: string} */
    private function resolveMaterial(array $material): array
    {
        $row = (int) $material['master_row'];
        if (array_key_exists($row, $this->resolved)) {
            return [$this->resolved[$row], $this->resolved[$row] ? 'exact' : 'created'];
        }

        $found = RawMaterial::where('production_id', $this->production->id)
            ->where('source_id', 'costings:master:'.$row)
            ->first();
        $how = 'exact';

        if (!$found && !empty($material['aiku_code'])) {
            $found = RawMaterial::where('production_id', $this->production->id)
                ->whereRaw('upper(code) = ?', [strtoupper($material['aiku_code'])])
                ->first();
            $how = 'alias';
        }

        if (!$found && !empty($material['code'])) {
            $found = RawMaterial::where('production_id', $this->production->id)
                ->whereRaw('upper(code) = ?', [strtoupper($material['code'])])
                ->first();
        }

        if (!$found) {
            $key        = $this->normaliseName($material['name']);
            $candidates = $this->byNormalisedName[$key] ?? [];
            if ($candidates) {
                $found = $this->preferFamily($candidates, $material['family'] ?? null);
            } else {
                [$found, $how] = $this->fuzzyMatch($key, $material['family'] ?? null);
            }
        }

        $this->resolved[$row] = $found;

        return [$found, $found ? ($found->source_id === 'costings:master:'.$row || $how !== 'alias' ? $how : 'alias') : 'created'];
    }

    /** @return array{0: ?RawMaterial, 1: string} */
    private function fuzzyMatch(string $key, ?string $family): array
    {
        $tokens = array_filter(explode(' ', $key));
        if (!$tokens) {
            return [null, 'created'];
        }
        $first      = reset($tokens);
        $candidates = [];
        foreach ($this->byNormalisedName as $otherKey => $rawMaterials) {
            $otherTokens = array_filter(explode(' ', $otherKey));
            if (!$otherTokens || reset($otherTokens) !== $first) {
                continue;
            }
            $subset = !array_diff($tokens, $otherTokens) || !array_diff($otherTokens, $tokens);
            if (!$subset) {
                continue;
            }
            foreach ($rawMaterials as $rawMaterial) {
                if ($family === null || Str::startsWith(strtoupper($rawMaterial->code), strtoupper($family))) {
                    $candidates[] = $rawMaterial;
                }
            }
        }
        if (count($candidates) !== 1) {
            return [null, 'created'];
        }

        return [$candidates[0], 'auto'];
    }

    private function preferFamily(array $candidates, ?string $family): RawMaterial
    {
        if ($family) {
            foreach ($candidates as $candidate) {
                if (Str::startsWith(strtoupper($candidate->code), strtoupper($family))) {
                    return $candidate;
                }
            }
        }

        return $candidates[0];
    }

    private function createRawMaterial(array $material): RawMaterial
    {
        $code = $material['code'] ?: 'CST-'.$material['master_row'];
        $code = preg_replace('/[^A-Za-z0-9_-]/', '-', $code);
        if (RawMaterial::where('organisation_id', $this->production->organisation_id)->whereRaw('upper(code) = ?', [strtoupper($code)])->exists()) {
            $code = 'CST-'.$material['master_row'];
        }

        $rawMaterial = StoreRawMaterial::make()->action($this->production, [
            'type'        => RawMaterialTypeEnum::STOCK->value,
            'code'        => $code,
            'description' => Str::limit($material['name'], 255, ''),
            'unit'        => $material['unit'],
            'unit_cost'   => (float) $material['cost'],
            'source_id'   => 'costings:master:'.$material['master_row'],
        ]);
        $rawMaterial->update(['data' => array_filter(['cas' => $material['cas'] ?? null, 'inci' => $material['inci'] ?? null, 'section' => $material['section'] ?? null])]);
        $this->byNormalisedName[$this->normaliseName($material['name'])][] = $rawMaterial;

        return $rawMaterial;
    }

    private function indexRawMaterials(): void
    {
        RawMaterial::where('production_id', $this->production->id)
            ->where('description', 'not ilike', '%not for resale%')
            ->each(function (RawMaterial $rawMaterial) {
                $this->byNormalisedName[$this->normaliseName($rawMaterial->description)][] = $rawMaterial;
            });
    }

    private function normaliseName(string $name): string
    {
        $name = strtolower(preg_replace('/\(.*?\)|\[.*?\]/', '', $name));
        $name = preg_replace('/[^a-z ]/', ' ', $name);
        $tokens = array_diff(preg_split('/\s+/', trim($name)) ?: [], self::STOP_WORDS, ['']);

        return implode(' ', $tokens);
    }

    private function findArtefact(string $code): ?Artefact
    {
        return Artefact::where('production_id', $this->production->id)
            ->whereRaw('upper(code) = ?', [strtoupper($code)])
            ->first();
    }

    private function existingRecipeLines(Artefact $artefact)
    {
        $stepIds = ArtefactManufactureTask::where('artefact_id', $artefact->id)->pluck('id');

        return \App\Models\Production\RecipeStepRawMaterial::whereIn('artefact_manufacture_task_id', $stepIds)->with('rawMaterial')->get();
    }

    private function compareLines(string $code, $existing, array $resolvedLines): array
    {
        $rows       = [];
        $sheetByRaw = collect($resolvedLines)->keyBy(fn ($l) => $l['raw_material']->id);
        foreach ($existing as $line) {
            $sheet  = $sheetByRaw->get($line->raw_material_id);
            $rows[] = [$code, $line->rawMaterial->code, $line->quantity_per_unit, $sheet['quantity_per_unit'] ?? null, $sheet ? (abs($sheet['quantity_per_unit'] - $line->quantity_per_unit) > 1e-6 ? 'quantity differs' : 'same') : 'only in aiku'];
        }
        foreach ($resolvedLines as $line) {
            if (!$existing->contains('raw_material_id', $line['raw_material']->id)) {
                $rows[] = [$code, $line['raw_material']->code, null, $line['quantity_per_unit'], 'only in workbook'];
            }
        }

        return $rows;
    }

    private function recipeStep(Artefact $artefact): ArtefactManufactureTask
    {
        $task = ManufactureTask::where('production_id', $this->production->id)->where('code', 'PROD')->first()
            ?? StoreManufactureTask::make()->action($this->production, [
                'code'                            => 'PROD',
                'name'                            => 'Production',
                'task_materials_cost'             => 0,
                'task_energy_cost'                => 0,
                'task_other_cost'                 => 0,
                'task_work_cost'                  => 0,
                'task_lower_target'               => 0,
                'task_upper_target'               => 0,
                'operative_reward_terms'          => ManufactureTaskOperativeRewardTermsEnum::NEVER,
                'operative_reward_allowance_type' => ManufactureTaskOperativeRewardAllowanceTypeEnum::ON_TOP_SALARY,
                'operative_reward_amount'         => 0,
            ]);

        $artefact->manufactureTasks()->syncWithoutDetaching([$task->id => ['position' => 1, 'units_per_artefact' => 1]]);

        return ArtefactManufactureTask::where('artefact_id', $artefact->id)->where('manufacture_task_id', $task->id)->firstOrFail();
    }

    private function writeCsv(string $file, array $header, array $rows): void
    {
        $handle = fopen($this->reviewDir.'/'.$file, 'w');
        fputcsv($handle, $header);
        foreach ($rows as $row) {
            fputcsv($handle, $row);
        }
        fclose($handle);
        $this->line("  → {$this->reviewDir}/$file (".count($rows).' rows)');
    }
}
