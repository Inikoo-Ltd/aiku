<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 14 Aug 2026 09:00:00 Central European Summer Time, Mijas, Spain
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Console\Commands;

use App\Actions\Production\ManufactureTask\StoreManufactureTask;
use App\Enums\Production\ManufactureTask\ManufactureTaskOperativeRewardAllowanceTypeEnum;
use App\Enums\Production\ManufactureTask\ManufactureTaskOperativeRewardTermsEnum;
use App\Models\Production\ManufacturePayBand;
use App\Models\Production\ManufactureTask;
use App\Models\Production\Production;
use Illuminate\Console\Command;
use PhpOffice\PhpSpreadsheet\Reader\Xlsx;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class ImportManufactureRewardSheet extends Command
{
    protected $signature = 'manufacture:import-reward-sheet
                           {production : Production slug}
                           {file : Path to the reward xlsx}
                           {--dry-run : Report without writing anything}
                           {--create-missing : Create manufacture tasks for sheet families with no matching task}';

    protected $description = 'Seed pay bands and standard rates for a production from management\'s reward spreadsheet';

    private const BANDS = [
        ['code' => '0', 'name' => 'Band 0', 'hourly_rate' => 12.71, 'has_multiplier' => true],
        ['code' => '1', 'name' => 'Band 1', 'hourly_rate' => 13.00, 'has_multiplier' => true],
        ['code' => '2', 'name' => 'Band 2', 'hourly_rate' => 14.00, 'has_multiplier' => true],
        ['code' => '3', 'name' => 'Band 3', 'hourly_rate' => 15.00, 'has_multiplier' => true],
        ['code' => 'D', 'name' => 'Development', 'hourly_rate' => 13.30, 'has_multiplier' => false],
        ['code' => 'DG', 'name' => 'Development Group', 'hourly_rate' => 15.00, 'has_multiplier' => false],
    ];

    public function handle(): int
    {
        $production = Production::where('slug', $this->argument('production'))->first();

        if (!$production) {
            $this->error("Unknown production '{$this->argument('production')}'.");

            return Command::FAILURE;
        }

        $path = $this->argument('file');

        if (!is_readable($path)) {
            $this->error("Cannot read '{$path}'.");

            return Command::FAILURE;
        }

        $dryRun = (bool) $this->option('dry-run');

        $bandsSeeded = $this->seedBands($production, $dryRun);

        try {
            $sheetName = $this->findFamiliesSheetName($path);

            if ($sheetName === null) {
                $this->error("No sheet with a name containing 'Product Famil' found in '{$path}'.");

                return Command::FAILURE;
            }

            $reader = new Xlsx();
            $reader->setReadDataOnly(true);
            $sheet = $reader->load($path)->getSheetByName($sheetName);
        } catch (\Throwable $e) {
            $this->error("Unreadable spreadsheet: {$e->getMessage()}");

            return Command::FAILURE;
        }

        if (!$sheet) {
            $this->error("Sheet '{$sheetName}' could not be loaded from '{$path}'.");

            return Command::FAILURE;
        }

        $result = $this->importFamilies($production, $sheet, $dryRun, (bool) $this->option('create-missing'));

        $this->report($bandsSeeded, $result, $dryRun);

        return Command::SUCCESS;
    }

    private function findFamiliesSheetName(string $path): ?string
    {
        $names = (new Xlsx())->listWorksheetNames($path);

        foreach ($names as $name) {
            if (stripos($name, 'Product Famil') !== false) {
                return $name;
            }
        }

        return null;
    }

    private function seedBands(Production $production, bool $dryRun): int
    {
        $effectiveFrom = now()->startOfYear();
        $seeded        = 0;

        foreach (self::BANDS as $band) {
            $multiplier = $band['has_multiplier'] ? round($band['hourly_rate'] / 12.71, 6) : null;

            if ($dryRun) {
                $seeded++;

                continue;
            }

            ManufacturePayBand::query()->firstOrCreate(
                [
                    'production_id'  => $production->id,
                    'code'           => $band['code'],
                    'effective_from' => $effectiveFrom,
                ],
                [
                    'group_id'          => $production->group_id,
                    'organisation_id'   => $production->organisation_id,
                    'name'              => $band['name'],
                    'hourly_rate'       => $band['hourly_rate'],
                    'target_multiplier' => $multiplier,
                    'requires_approval' => false,
                ]
            );
            $seeded++;
        }

        return $seeded;
    }

    /**
     * @return array{updated: int, skipped: int, created: int, orphans: array<int, string>, overrides: array<int, string>, warnings: array<int, string>}
     */
    private function importFamilies(Production $production, Worksheet $sheet, bool $dryRun, bool $createMissing = false): array
    {
        $headerRow = $this->findHeaderRow($sheet);

        if ($headerRow === null) {
            return ['updated' => 0, 'skipped' => 0, 'created' => 0, 'orphans' => [], 'overrides' => [], 'warnings' => ["Could not locate a header row with 'Family' and 'Piece Rate' columns."]];
        }

        $columns = $this->mapColumns($sheet, $headerRow);

        if (!isset($columns['family'], $columns['piece_rate'])) {
            return ['updated' => 0, 'skipped' => 0, 'created' => 0, 'orphans' => [], 'overrides' => [], 'warnings' => ["Could not locate 'Family' or 'Piece Rate (Old)' columns."]];
        }

        $tasks = ManufactureTask::where('production_id', $production->id)
            ->get()
            ->keyBy(fn (ManufactureTask $task) => strtolower($task->code));

        $updated      = 0;
        $skipped      = 0;
        $created      = 0;
        $orphans      = [];
        $overrides    = [];
        $warnings     = [];

        $highestRow = $sheet->getHighestDataRow();

        for ($row = $headerRow + 1; $row <= $highestRow; $row++) {
            try {
                $code = trim((string) $sheet->getCell($columns['family'].$row)->getValue());
            } catch (\Throwable $e) {
                $warnings[] = "row {$row}: unreadable, skipped ({$e->getMessage()})";

                continue;
            }

            if ($code === '') {
                continue;
            }

            $pieceRate = $sheet->getCell($columns['piece_rate'].$row)->getValue();

            if (!is_numeric($pieceRate) || (float) $pieceRate <= 0) {
                $skipped++;

                continue;
            }

            $pieceRate    = (float) $pieceRate;
            $standardRate = round(13.00 / $pieceRate, 4);

            $task = $tasks->get(strtolower($code));

            if (!$task) {
                if (!$createMissing) {
                    $orphans[] = $code;

                    continue;
                }

                $name = '';

                if (isset($columns['description'])) {
                    $name = trim((string) $sheet->getCell($columns['description'].$row)->getValue());
                }

                if ($name === '') {
                    $name = $code;
                }

                if ($dryRun) {
                    $created++;

                    continue;
                }

                $upperTarget = round($standardRate * 15 / 12.71, 4);

                try {
                    $task = $this->storeTask($production, $code, $name, $standardRate, $upperTarget);
                } catch (\Illuminate\Validation\ValidationException $e) {
                    $warnings[] = "row {$row} ({$code}): not created — ".implode(' ', array_map(fn (array $messages) => implode(' ', $messages), $e->errors()));

                    continue;
                }

                $tasks->put(strtolower($code), $task);
                $created++;
            }

            $overrideReason = null;

            if (isset($columns['target_0'])) {
                try {
                    $sheetTarget0 = $sheet->getCell($columns['target_0'].$row)->getCalculatedValue();
                } catch (\Throwable $e) {
                    $warnings[]   = "row {$row}: target 0 formula unreadable, override check skipped";
                    $sheetTarget0 = null;
                }

                if (is_numeric($sheetTarget0)) {
                    $formulaTarget0 = (float) floor(13 / $pieceRate);

                    if (abs((float) $sheetTarget0 - $formulaTarget0) > 1) {
                        $overrideReason = "sheet target differs from formula (sheet: {$sheetTarget0}, formula: {$formulaTarget0})";
                        $overrides[]    = $code;
                    }
                }
            }

            if (!$dryRun) {
                $updateData = [
                    'standard_rate'          => $standardRate,
                    'target_override_reason' => $overrideReason,
                ];

                if ($task->name === $task->code && isset($columns['description'])) {
                    $sheetName = trim((string) $sheet->getCell($columns['description'].$row)->getValue());

                    if ($sheetName !== '') {
                        $updateData['name'] = $sheetName;
                    }
                }

                $task->update($updateData);
            }

            $updated++;
        }

        return [
            'updated'   => $updated,
            'skipped'   => $skipped,
            'created'   => $created,
            'orphans'   => $orphans,
            'overrides' => $overrides,
            'warnings'  => $warnings,
        ];
    }

    private function storeTask(Production $production, string $code, string $name, float $standardRate, float $upperTarget): ManufactureTask
    {
        return StoreManufactureTask::make()->action($production, [
            'code'                            => $code,
            'name'                            => $name,
            'task_materials_cost'             => 0,
            'task_energy_cost'                => 0,
            'task_other_cost'                 => 0,
            'task_work_cost'                  => 0,
            'task_lower_target'               => $standardRate,
            'task_upper_target'               => $upperTarget,
            'operative_reward_terms'          => ManufactureTaskOperativeRewardTermsEnum::NEVER,
            'operative_reward_allowance_type' => ManufactureTaskOperativeRewardAllowanceTypeEnum::ON_TOP_SALARY,
            'operative_reward_amount'         => 0,
        ]);
    }

    private function findHeaderRow(Worksheet $sheet): ?int
    {
        $highestRow = min($sheet->getHighestDataRow(), 20);

        for ($row = 1; $row <= $highestRow; $row++) {
            for ($col = 1; $col <= 10; $col++) {
                $value = $sheet->getCellByColumnAndRow($col, $row)->getValue();

                if (is_string($value) && strtolower(trim($value)) === 'family') {
                    return $row;
                }
            }
        }

        return null;
    }

    /**
     * @return array<string, string>
     */
    private function mapColumns(Worksheet $sheet, int $headerRow): array
    {
        $map         = [];
        $highestCol  = $sheet->getHighestDataColumn($headerRow);
        $highestColIndex = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::columnIndexFromString($highestCol);

        for ($col = 1; $col <= $highestColIndex; $col++) {
            $value = $sheet->getCellByColumnAndRow($col, $headerRow)->getValue();

            if ($value === null || $value === '') {
                continue;
            }

            $letter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($col);
            $header = strtolower(trim((string) $value));

            if ($header === 'family') {
                $map['family'] = $letter;
            } elseif (str_starts_with($header, 'piece rate')) {
                $map['piece_rate'] = $letter;
            } elseif ($header === '0') {
                $map['target_0'] = $letter;
            } elseif ($header === 'description') {
                $map['description'] = $letter;
            }
        }

        if (!isset($map['description']) && isset($map['family'])) {
            $familyIndex = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::columnIndexFromString($map['family']);
            $candidate   = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($familyIndex + 1);

            if (!in_array($candidate, $map, true)) {
                $map['description'] = $candidate;
            }
        }

        return $map;
    }

    /**
     * @param array{updated: int, skipped: int, created: int, orphans: array<int, string>, overrides: array<int, string>, warnings: array<int, string>} $result
     */
    private function report(int $bandsSeeded, array $result, bool $dryRun): void
    {
        $this->newLine();
        $this->info($dryRun ? 'Dry run report:' : 'Import report:');
        $this->table(['Metric', 'Count'], [
            ['Pay bands seeded', $bandsSeeded],
            ['Tasks updated', $result['updated']],
            ['Tasks created', $result['created']],
            ['Rows skipped (no code / no piece rate)', $result['skipped']],
            ['Sheet families with no matching task', count($result['orphans'])],
            ['Detected target overrides', count($result['overrides'])],
        ]);

        foreach ($result['warnings'] as $warning) {
            $this->line("  <fg=yellow>warning</>: {$warning}");
        }

        if ($result['orphans'] !== []) {
            $this->line('Unmatched family codes: '.implode(', ', $result['orphans']));
        }

        if ($result['overrides'] !== []) {
            $this->line('Overridden target codes: '.implode(', ', $result['overrides']));
        }
    }
}
