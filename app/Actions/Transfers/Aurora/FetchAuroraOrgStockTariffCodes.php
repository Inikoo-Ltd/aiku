<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 27 Oct 2026, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Transfers\Aurora;

use App\Actions\Goods\TradeUnit\UpdateTradeUnit;
use App\Models\Inventory\OrgStock;
use App\Models\SysAdmin\Organisation;
use App\Transfers\AuroraOrganisationService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Laravel\Nightwatch\Facades\Nightwatch;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Each organisation's Aurora classifies its parts on its own, sometimes under another heading than
 * the trade unit (HELP-4343), so the code goes to org_stocks.tariff_code. An empty trade unit code
 * is filled from the organisation that created the trade unit.
 */
class FetchAuroraOrgStockTariffCodes
{
    use AsAction;

    public string $commandSignature = 'fetch:org_stock_tariff_codes {organisations?* : Organisation slugs, all with an Aurora when empty} {--fix : Write the codes, otherwise dry run} {--overwrite : Also replace SKO codes that differ from Aurora}';

    /**
     * @return array<int, array{organisation: string, code: string, current: string|null, aurora: string, target: string}>
     */
    public function handle(Organisation $organisation, bool $fix = false, bool $overwrite = false): array
    {
        $orgStocks = OrgStock::where('organisation_id', $organisation->id)
            ->whereNull('deleted_at')
            ->where('source_id', 'like', $organisation->id.':%')
            ->with('tradeUnits')
            ->get()
            ->keyBy(fn (OrgStock $orgStock) => explode(':', $orgStock->source_id)[1]);

        $auroraCodes = $this->getAuroraPartTariffCodes($organisation, $orgStocks->keys()->all());

        $rows = [];
        foreach ($auroraCodes as $partSku => $auroraCode) {
            $auroraCode = preg_replace('/\s/u', '', (string) $auroraCode);
            $orgStock   = $orgStocks->get($partSku);
            if (!$orgStock || !preg_match('/^\d{6,12}$/', $auroraCode)) {
                continue;
            }

            if ($orgStock->tariff_code !== $auroraCode && (blank($orgStock->tariff_code) || $overwrite)) {
                $rows[] = ['organisation' => $organisation->slug, 'code' => $orgStock->code, 'current' => $orgStock->tariff_code, 'aurora' => $auroraCode, 'target' => 'sko'];
                if ($fix) {
                    $orgStock->update(['tariff_code' => $auroraCode]);
                }
            }

            $tradeUnit = $orgStock->tradeUnits->first();
            if ($tradeUnit && blank($tradeUnit->tariff_code) && str_starts_with((string) $tradeUnit->source_id, $organisation->id.':')) {
                $rows[] = ['organisation' => $organisation->slug, 'code' => $tradeUnit->code, 'current' => null, 'aurora' => $auroraCode, 'target' => 'trade unit'];
                if ($fix) {
                    UpdateTradeUnit::make()->action($tradeUnit, ['tariff_code' => $auroraCode], strict: false);
                }
            }
        }

        return $rows;
    }

    /**
     * @param array<int, string> $partSkus
     *
     * @return array<string, string|null>
     */
    public function getAuroraPartTariffCodes(Organisation $organisation, array $partSkus): array
    {
        (new AuroraOrganisationService())->initialisation($organisation);

        $codes = [];
        foreach (array_chunk($partSkus, 1000) as $chunk) {
            foreach (DB::connection('aurora')->table('Part Dimension')->whereIn('Part SKU', $chunk)->pluck('Part Tariff Code', 'Part SKU') as $partSku => $code) {
                $codes[(string) $partSku] = $code;
            }
        }

        return $codes;
    }

    public function asCommand(Command $command): int
    {
        Nightwatch::dontSample();

        $organisations = $command->argument('organisations')
            ? Organisation::whereIn('slug', $command->argument('organisations'))->get()
            : Organisation::whereNotNull('source->db_name')->get();

        $rows = [];
        foreach ($organisations as $organisation) {
            $rows = [...$rows, ...$this->handle($organisation, (bool) $command->option('fix'), (bool) $command->option('overwrite'))];
        }

        $command->table(['organisation', 'code', 'current', 'aurora', 'target'], $rows);
        $command->info(($command->option('fix') ? 'Applied' : 'Dry run').': '.count($rows).' codes');

        return 0;
    }
}
