<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 03 Sep 2026 16:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Goods\TradeUnit;

use App\Models\Goods\TradeUnit;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\LazyCollection;
use Laravel\Nightwatch\Facades\Nightwatch;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * The live Aurora fetch never carried the tariff code and the goods freeze (17 Aug 2026)
 * removed the gap-fill, so codes classified in Aurora stay behind. The Aurora history
 * import stores the latest Aurora code per trade unit in the audits, which is the source here.
 */
class RepairTradeUnitTariffCodesFromAurora
{
    use AsAction;

    public string $commandSignature = 'repair:trade_unit_tariff_codes_from_aurora {--fix : Write the codes, otherwise dry run} {--overwrite : Also replace codes that differ from Aurora, otherwise only fill empty ones} {--csv= : Write the dry-run rows to this file}';

    /**
     * @return array<int, array{code: string, organisation: string, current: string|null, aurora: string, action: string}>
     */
    public function handle(bool $fix = false, bool $overwrite = false): array
    {
        $rows = [];

        foreach ($this->getCandidates() as $candidate) {
            $current = $candidate->tariff_code;
            $aurora  = trim($candidate->aurora_code);
            $isEmpty = $current === null || trim($current) === '';

            if (!preg_match('/^\d{6,10}$/', str_replace(' ', '', $aurora))) {
                continue;
            }
            if (!$isEmpty && str_replace(' ', '', $current) == str_replace(' ', '', $aurora)) {
                continue;
            }
            if (!$isEmpty && !$overwrite) {
                continue;
            }

            $rows[] = [
                'code'         => $candidate->code,
                'organisation' => $candidate->organisation,
                'current'      => $current,
                'aurora'       => $aurora,
                'action'       => $isEmpty ? 'fill' : 'overwrite',
            ];

            if ($fix) {
                UpdateTradeUnit::make()->action(TradeUnit::find($candidate->id), ['tariff_code' => $aurora], strict: false);
            }
        }

        return $rows;
    }

    private function getCandidates(): LazyCollection
    {
        $latestAuroraCode = DB::table('audits')
            ->select('auditable_id', DB::raw("new_values->>'tariff_code' as aurora_code"))
            ->whereIn('auditable_type', ['TradeUnit', 'App\Models\Goods\TradeUnit'])
            ->whereRaw("jsonb_exists(new_values, 'tariff_code')")
            ->orderBy('auditable_id')
            ->orderByDesc('created_at')
            ->distinct('auditable_id');

        return DB::table('trade_units as tu')
            ->joinSub($latestAuroraCode, 'la', 'la.auditable_id', '=', 'tu.id')
            ->leftJoin('organisations as o', DB::raw('o.id::text'), '=', DB::raw("split_part(tu.source_id, ':', 1)"))
            ->whereNull('tu.deleted_at')
            ->where('tu.status', 'active')
            ->select('tu.id', 'tu.code', 'tu.tariff_code', 'la.aurora_code', DB::raw("COALESCE(o.code, '?') as organisation"))
            ->orderBy('tu.code')
            ->cursor();
    }

    public function asCommand(Command $command): int
    {
        Nightwatch::dontSample();

        $fix       = (bool) $command->option('fix');
        $overwrite = (bool) $command->option('overwrite');
        $rows      = $this->handle($fix, $overwrite);

        if ($csv = $command->option('csv')) {
            $handle = fopen($csv, 'w');
            fputcsv($handle, ['code', 'organisation', 'current', 'aurora', 'action']);
            foreach ($rows as $row) {
                fputcsv($handle, $row);
            }
            fclose($handle);
            $command->info(count($rows).' rows written to '.$csv);
        } elseif ($rows) {
            $command->table(['code', 'organisation', 'current', 'aurora', 'action'], $rows);
        }

        $fills      = count(array_filter($rows, fn ($row) => $row['action'] == 'fill'));
        $overwrites = count($rows) - $fills;
        $command->info(($fix ? 'Applied' : 'Dry run').": $fills filled, $overwrites overwritten".($overwrite ? '' : ' (pass --overwrite to include codes that differ from Aurora)'));

        return 0;
    }
}
