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
 * tariff_codes as 8 or 10 digit rows under their 6 digit parent, never overwriting a name
 * already edited in aiku.
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

        $this->repairCodesThatLostTheirLeadingZero();

        $rows = DB::connection('aurora')->select(
            'select `Commodity Code` as code, `Commodity Description` as description, `Commodity Name` as name from kbase.`Commodity Code Dimension` where `Commodity Name` is not null and `Commodity Name` <> ""'
        );

        foreach ($rows as $row) {
            $hsCode = $this->normaliseCode((string)$row->code);
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
                    'level'       => strlen($hsCode),
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

    /**
     * Aurora stored the code as an integer, so "0902300000" came back as 902300000. Codes are
     * either 8 or 10 digits, which tells us how far to pad.
     */
    public function normaliseCode(string $code): string
    {
        return str_pad($code, strlen($code) > 8 ? 10 : 8, '0', STR_PAD_LEFT);
    }

    protected function repairCodesThatLostTheirLeadingZero(): void
    {
        TariffCode::where('level', '>=', 8)->get()->each(function (TariffCode $tariffCode) {
            $hsCode = $this->normaliseCode($tariffCode->hs_code);
            if ($hsCode !== $tariffCode->hs_code || $tariffCode->level !== strlen($hsCode)) {
                $tariffCode->update(['hs_code' => $hsCode, 'level' => strlen($hsCode)]);
            }
        });
    }

    public function asCommand(Command $command): int
    {
        Nightwatch::dontSample();
        $stats = $this->handle();
        $command->info("new rows: {$stats['new']}, names set: {$stats['named']}, skipped (no 6 digit parent): {$stats['skipped']}");

        return 0;
    }
}
