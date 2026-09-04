<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Transfers\Aurora;

use App\Models\Helpers\TariffCode;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Laravel\Nightwatch\Facades\Nightwatch;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Aurora kept hand curated short names ("Incense", "Candles") for 8 digit commodity codes in its
 * shared kbase; they are what its export invoices print per tariff line. This copies them into
 * tariff_codes as level 8 rows, never overwriting a name already edited in aiku.
 */
class FetchAuroraTariffCodeNames
{
    use AsAction;

    public string $commandSignature   = 'fetch:tariff_code_names';
    public string $commandDescription = 'Copy Aurora kbase commodity names into tariff_codes';

    /**
     * @return array{new: int, named: int, skipped: int}
     */
    public function handle(): array
    {
        $stats = ['new' => 0, 'named' => 0, 'skipped' => 0];

        $rows = DB::connection('aurora')->select(
            'select `Commodity Code` as code, `Commodity Description` as description, `Commodity Name` as name from kbase.`Commodity Code Dimension` where `Commodity Name` is not null and `Commodity Name` <> ""'
        );

        foreach ($rows as $row) {
            $hsCode = str_pad((string)$row->code, 8, '0', STR_PAD_LEFT);
            $parent = TariffCode::where('hs_code', substr($hsCode, 0, 6))->first();
            if (!$parent) {
                $stats['skipped']++;
                continue;
            }

            $tariffCode = TariffCode::firstOrNew(['hs_code' => $hsCode]);
            if (!$tariffCode->exists) {
                $tariffCode->fill([
                    'section'     => $parent->section,
                    'description' => $row->description ?: $parent->description,
                    'parent_id'   => $parent->id,
                    'level'       => 8,
                ]);
                $stats['new']++;
            }
            if (!$tariffCode->name) {
                $tariffCode->name = trim($row->name);
                $stats['named']++;
            }
            $tariffCode->save();
        }

        return $stats;
    }

    public function asCommand(Command $command): int
    {
        Nightwatch::dontSample();
        $stats = $this->handle();
        $command->info("new rows: {$stats['new']}, names set: {$stats['named']}, skipped (no 6 digit parent): {$stats['skipped']}");

        return 0;
    }
}
