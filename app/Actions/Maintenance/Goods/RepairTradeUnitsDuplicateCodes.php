<?php
/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 07 Nov 2025 15:13:23 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 07 Nov 2025 12:25:00 Kuala Lumpur, Malaysia
 */

namespace App\Actions\Maintenance\Goods;

use App\Actions\Goods\TradeUnit\DeleteTradeUnit;
use App\Models\Goods\TradeUnit;
use App\Models\Goods\TradeUnitStats;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

class RepairTradeUnitsDuplicateCodes
{
    use AsAction;

    public function getCommandSignature(): string
    {
        return 'repair:trade_units_duplicate_codes {--include-trashed}';
    }

    public function handle(bool $includeTrashed = false): array
    {
        $base = DB::table('trade_units');

        if (!$includeTrashed && DB::getSchemaBuilder()->hasColumn('trade_units', 'deleted_at')) {
            $base->whereNull('deleted_at');
        }


        $duplicateCodes = (clone $base)
            ->whereNotNull('code')
            ->select('code', DB::raw('COUNT(*) as total'))
            ->groupBy('code')
            ->havingRaw('COUNT(*) > 1')
            ->orderBy('total', 'desc')
            ->pluck('code')
            ->all();

        $results = [];
        if (empty($duplicateCodes)) {
            return $results; // none
        }

        // Fetch rows for each duplicate code
        foreach (array_chunk($duplicateCodes, 200) as $chunk) {
            $rows = (clone $base)
                ->whereIn('code', $chunk)
                ->orderBy('code')
                ->orderBy('id')
                ->get([
                    'id',
                    'code',
                    'name',
                    'status',
                    'source_id',
                    'deleted_at'
                ]);

            foreach ($rows->groupBy('code') as $code => $group) {
                $results[$code] = $group->map(function ($row) {
                    $numberModels = DB::table('model_has_trade_units')->where('trade_unit_id', $row->id)->count();
                    $numberImages = DB::table('model_has_media')->where('model_type', 'TradeUnit')->where('model_id', $row->id)->count();

                    $tradeInitStats = TradeUnitStats::where('trade_unit_id', $row->id)->first();

                    if ($numberModels == 0 && $numberImages == 0) {
                        $tradeUnit = TradeUnit::where('id', $row->id)->first();
                        DeleteTradeUnit::run(
                            $tradeUnit,
                            [
                                'hard'  => true,
                                'force' => true,
                            ]
                        );
                    }


                    return [
                        'id'            => $row->id,
                        'source_id'     => $row->source_id,
                        'name'          => $row->name,
                        'status'        => $row->status,
                        'deleted_at'    => $row->deleted_at,
                        'number_models' => $numberModels,
                        'number_images' => $numberImages,
                        'number_stocks' => $tradeInitStats->number_stocks,
                    ];
                })->values()->all();
            }
        }

        ksort($results);

        return $results;
    }

    public function asCommand(Command $command): int
    {
        $includeTrashed = (bool)$command->option('include-trashed');

        $command->info('Scanning for duplicate Trade Unit codes...');

        $results = $this->handle($includeTrashed);

        if (empty($results)) {
            $command->info('No duplicate codes found.');

            return 0;
        }

        $counter = 0;
        foreach ($results as $code => $rows) {
            $command->newLine();
            $counter++;
            $command->line("<info>Code:</info> {$code}  <comment>(".count($rows).")</comment>");
            $command->table(
                ['#', 'ID', 'Source ID', 'Name', 'Status', 'Models', 'Images', 'Stocks', 'Deleted at'],
                array_map(function ($r) use (&$counter) {
                    return [
                        $counter,
                        $r['id'],
                        $r['source_id'],
                        $r['name'],
                        $r['status'],
                        $r['number_models'],
                        $r['number_images'],
                        $r['number_stocks'],
                        $r['deleted_at']
                    ];
                }, $rows)
            );
        }

        $command->newLine();
        $command->info('Done.');

        return 0;
    }
}
