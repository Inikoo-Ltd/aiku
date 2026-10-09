<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 10 Oct 2026 16:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Goods\Packaging;

use App\Actions\Goods\TradeUnit\UpdateTradeUnit;
use App\Enums\Goods\Packaging\PackagingBrandOwnershipEnum;
use App\Enums\Goods\Packaging\PackagingFamilySourceEnum;
use App\Enums\Goods\Packaging\PackagingLevelEnum;
use App\Enums\Goods\Packaging\PackagingMaterialCategoryEnum;
use App\Models\Goods\PackagingComponent;
use App\Models\Goods\PackagingFamily;
use App\Models\Goods\TradeUnit;
use App\Models\SysAdmin\Organisation;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Laravel\Nightwatch\Facades\Nightwatch;
use Lorisleiva\Actions\Concerns\AsAction;
use PhpOffice\PhpSpreadsheet\IOFactory;

/**
 * Loads the packaging weights of the UK packaging workbook (Products and AWA-Family sheets) as one legacy packaging
 * family per SKO: a component per material with grams, divided by the trade units in the SKO so the weight is per
 * unit. Families from the AWA-Family prefixes are own brand. Candles (tariff 3406) lose their glass, as the workbook's
 * macro did, because the jar is the product. Trade units that already have packaging from anywhere else are left alone.
 */
class ImportLegacyUkPackaging
{
    use AsAction;

    public string $commandSignature = 'epr:import-legacy-uk-packaging {file : the UK packaging workbook (xlsx)} {organisation=aw : organisation whose SKO codes the sheet uses} {--write : save; without it nothing is written}';

    public const array MATERIALS = [
        7  => PackagingMaterialCategoryEnum::PLASTIC,
        8  => PackagingMaterialCategoryEnum::GLASS,
        9  => PackagingMaterialCategoryEnum::PAPER_CARDBOARD,
        10 => PackagingMaterialCategoryEnum::ALUMINIUM,
        11 => PackagingMaterialCategoryEnum::STEEL,
        12 => PackagingMaterialCategoryEnum::WOOD,
        13 => PackagingMaterialCategoryEnum::OTHER,
    ];

    /**
     * @return array<string, mixed>
     */
    public function handle(Organisation $organisation, string $path, bool $write = false): array
    {
        [$skos, $awaPrefixes] = $this->read($path);

        $stats = ['sheet_rows' => count($skos), 'no_weights' => 0, 'candle_glass_removed' => [], 'sko_not_in_aiku' => [], 'sko_without_trade_unit' => 0,
                  'trade_unit_has_other_packaging' => 0, 'trade_unit_conflicts' => 0, 'own_brand' => 0, 'families' => 0];

        $orgStocks = DB::table('org_stocks')
            ->leftJoin('model_has_trade_units', fn ($join) => $join->on('model_has_trade_units.model_id', 'org_stocks.id')->where('model_has_trade_units.model_type', 'OrgStock'))
            ->where('org_stocks.organisation_id', $organisation->id)
            ->whereIn(DB::raw('lower(org_stocks.code)'), array_keys($skos))
            ->get(['org_stocks.code', 'model_has_trade_units.trade_unit_id', 'model_has_trade_units.quantity'])
            ->keyBy(fn ($row) => mb_strtolower($row->code));

        $byTradeUnit = [];
        foreach ($skos as $key => $sko) {
            if ($sko['candle'] && $sko['grams'][8] > 0) {
                $stats['candle_glass_removed'][] = $sko['code'];
                $sko['grams'][8] = 0;
            }
            if (array_sum($sko['grams']) <= 0) {
                $stats['no_weights']++;
                continue;
            }
            $orgStock = $orgStocks->get($key);
            if (!$orgStock) {
                $stats['sko_not_in_aiku'][] = $sko['code'];
                continue;
            }
            if (!$orgStock->trade_unit_id || (float)$orgStock->quantity <= 0) {
                $stats['sko_without_trade_unit']++;
                continue;
            }

            $sko['units'] = (float)$orgStock->quantity;
            $current      = $byTradeUnit[$orgStock->trade_unit_id] ?? null;
            if ($current) {
                $stats['trade_unit_conflicts']++;
                if ($current['units'] <= $sko['units']) {
                    continue;
                }
            }
            $byTradeUnit[$orgStock->trade_unit_id] = $sko;
        }

        $tradeUnits = TradeUnit::whereIn('id', array_keys($byTradeUnit))->with('packagingFamily')->get();
        foreach ($tradeUnits as $tradeUnit) {
            if ($tradeUnit->packagingFamily && $tradeUnit->packagingFamily->source !== PackagingFamilySourceEnum::LEGACY_UK_2026) {
                $stats['trade_unit_has_other_packaging']++;
                continue;
            }

            $sko      = $byTradeUnit[$tradeUnit->id];
            $ownBrand = isset($awaPrefixes[mb_strtolower(explode('-', $sko['code'])[0])]);
            $stats['own_brand'] += (int)$ownBrand;
            $stats['families']++;

            if ($write) {
                DB::transaction(fn () => $this->store($tradeUnit, $sko, $ownBrand));
            }
        }

        return $stats;
    }

    /**
     * @param array{code: string, name: string|null, grams: array<int, float>, units: float} $sko
     */
    private function store(TradeUnit $tradeUnit, array $sko, bool $ownBrand): void
    {
        $family = $tradeUnit->packagingFamily ?? PackagingFamily::create([
            'group_id'  => $tradeUnit->group_id,
            'code'      => $sko['code'],
            'name'      => $sko['name'],
            'status'    => 'active',
            'signature' => sha1('legacy_uk_2026|'.$tradeUnit->id),
        ]);
        $family->update([
            'source'          => PackagingFamilySourceEnum::LEGACY_UK_2026,
            'brand_ownership' => $ownBrand ? PackagingBrandOwnershipEnum::OWN_BRAND : PackagingBrandOwnershipEnum::UNBRANDED,
        ]);

        $components = [];
        foreach (self::MATERIALS as $column => $material) {
            if ($sko['grams'][$column] <= 0) {
                continue;
            }
            $weight    = round($sko['grams'][$column] / $sko['units'], 3);
            $signature = sha1("legacy_uk_2026|$material->value|$weight");
            $component = PackagingComponent::firstOrCreate(
                ['group_id' => $tradeUnit->group_id, 'signature' => $signature],
                [
                    'name'               => $material->labels()[$material->value],
                    'packaging_level'    => PackagingLevelEnum::PRIMARY,
                    'material_category'  => $material,
                    'weight_g'           => $weight,
                    'weight_is_measured' => false,
                ]
            );
            $components[$component->id] = ['quantity' => 1, 'quantity_per_unit' => 1];
        }
        $family->components()->sync($components);

        if ($tradeUnit->packaging_family_id !== $family->id) {
            UpdateTradeUnit::make()->action($tradeUnit, ['packaging_family_id' => $family->id], strict: false);
        }
    }

    /**
     * @return array{array<string, array{code: string, name: string|null, candle: bool, grams: array<int, float>}>, array<string, true>}
     */
    private function read(string $path): array
    {
        $reader = IOFactory::createReader('Xlsx');
        $reader->setReadDataOnly(true);
        $reader->setLoadSheetsOnly(['Products', 'AWA-Family']);
        $book = $reader->load($path);

        $skos = [];
        foreach (array_slice($book->getSheetByName('Products')->toArray(null, true, false, false), 1) as $row) {
            $code = trim((string)$row[0]);
            if ($code === '') {
                continue;
            }
            $skos[mb_strtolower($code)] = [
                'code'   => $code,
                'name'   => $row[1] ? trim((string)$row[1]) : null,
                'candle' => str_starts_with(trim((string)$row[2]), '3406'),
                'grams'  => array_map(fn (int $column) => max(0.0, (float)$row[$column]), array_combine(array_keys(self::MATERIALS), array_keys(self::MATERIALS))),
            ];
        }

        $awaPrefixes = [];
        foreach ($book->getSheetByName('AWA-Family')->toArray(null, true, false, false) as $row) {
            if (trim((string)$row[0]) !== '') {
                $awaPrefixes[mb_strtolower(trim((string)$row[0]))] = true;
            }
        }

        return [$skos, $awaPrefixes];
    }

    public function asCommand(Command $command): int
    {
        Nightwatch::dontSample();
        ini_set('memory_limit', '2G');

        $organisation = Organisation::where('slug', $command->argument('organisation'))->firstOrFail();
        $stats        = $this->handle($organisation, $command->argument('file'), (bool)$command->option('write'));

        $command->table(['', ''], [
            ['Rows in Products', $stats['sheet_rows']],
            ['Rows with no weights', $stats['no_weights']],
            ['Candles with glass taken off', count($stats['candle_glass_removed'])],
            ['SKO not in '.$organisation->slug, count($stats['sko_not_in_aiku'])],
            ['SKO without trade unit', $stats['sko_without_trade_unit']],
            ['Trade unit already with other packaging', $stats['trade_unit_has_other_packaging']],
            ['Trade unit in several SKOs (smallest pack used)', $stats['trade_unit_conflicts']],
            ['Families '.($command->option('write') ? 'saved' : 'to save'), $stats['families']],
            ['of which own brand', $stats['own_brand']],
        ]);
        $command->line('Candles: '.implode(' ', array_slice($stats['candle_glass_removed'], 0, 40)));
        $command->line('Not in Aiku (first 40): '.implode(' ', array_slice($stats['sko_not_in_aiku'], 0, 40)));

        return 0;
    }
}
