<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 10 Oct 2026 14:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Reports;

use App\Enums\Goods\Packaging\EprActivityEnum;
use App\Enums\Goods\Packaging\PackagingFamilySourceEnum;
use App\Models\SysAdmin\Organisation;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Which SKOs moved in a period without packaging weights an EPR return can use, ranked by the SKOs moved, so the people
 * keeping packaging data fix what weighs most on the return first; and the checks on the weights they do have.
 */
class GetEprPackagingCompleteness
{
    use AsAction;

    public const int ROWS = 300;

    public const int TOP_SKOS = 500;

    public const int CHECK_ROWS = 50;

    public const float WEIGHT_TOLERANCE = 0.05;

    /**
     * Trade unit weights are whole grams, so a few grams either way on light packaging is rounding, not an error.
     */
    public const float WEIGHT_TOLERANCE_G = 2.0;

    public const array STATUSES = ['no_trade_unit', 'no_packaging', 'no_components', 'legacy', 'complete'];

    /**
     * @return array<string, mixed>
     */
    public function handle(Organisation $organisation, Carbon $from, Carbon $to): array
    {
        $skos     = $this->skos($organisation, $from, $to);
        $days     = (int)$from->diffInDays($to) + 1;
        $previous = $this->skos($organisation, $from->copy()->subDays($days), $from->copy()->subDay());

        $units           = $skos->sum('units');
        $unitsByStatus   = collect(self::STATUSES)->mapWithKeys(fn (string $status) => [$status => (float)$skos->where('status', $status)->sum('units')]);
        $toFix           = $skos->whereNotIn('status', ['complete'])->sortByDesc('units')->values();

        return [
            'from'     => $from->toDateString(),
            'to'       => $to->toDateString(),
            'has_data' => $skos->isNotEmpty(),
            'summary'  => [
                'units'             => (float)$units,
                'skos'              => $skos->count(),
                'coverage'          => $this->coverage($skos),
                'previous_coverage' => $previous->isNotEmpty() ? $this->coverage($previous) : null,
                'units_by_status'   => $unitsByStatus,
                'skos_by_status'    => collect(self::STATUSES)->mapWithKeys(fn (string $status) => [$status => $skos->where('status', $status)->count()]),
            ],
            'activities' => $this->activities($organisation, $from, $to),
            'checks'     => $this->checks($skos),
            'rows'       => $toFix->take(self::ROWS)->map(fn (array $sko) => [
                ...$sko,
                'share' => $units > 0 ? round($sko['units'] / $units * 100, 2) : 0,
            ])->all(),
            'rows_total' => $toFix->count(),
        ];
    }

    /**
     * The checks of section 2.5 of the EPR brief on the SKOs that moved, each with its SKOs ranked by SKOs moved:
     * old UK sheet weights among the most moved, packaging that does not weigh what the trade unit's gross less net
     * weight says, plastic without a polymer, and packaging marked as the product itself that is more than glass.
     *
     * @return list<array{key: string, count: int, rows: list<array<string, mixed>>}>
     */
    private function checks(Collection $skos): array
    {
        $ranked     = $skos->sortByDesc('units')->values();
        $tradeUnits = $ranked->pluck('trade_unit_id')->filter()->unique()->values()->all();

        $packaging = collect($tradeUnits === [] ? [] : DB::select(
            <<<'SQL'
            SELECT tu.id, tu.gross_weight, tu.net_weight, pf.is_product_itself,
                SUM(pc.weight_g * fhc.quantity_per_unit) AS packaging_g,
                SUM(pc.weight_g * fhc.quantity_per_unit) FILTER (WHERE pc.material_category = 'plastic' AND pc.polymer IS NULL) AS plastic_without_polymer_g,
                STRING_AGG(DISTINCT pc.material_category, ', ') FILTER (WHERE pc.material_category <> 'glass') AS other_than_glass
            FROM trade_units tu
            JOIN packaging_families pf ON pf.id = tu.packaging_family_id
            JOIN packaging_family_has_components fhc ON fhc.packaging_family_id = pf.id
            JOIN packaging_components pc ON pc.id = fhc.packaging_component_id
            WHERE tu.id = ANY(?::int[])
            GROUP BY tu.id, tu.gross_weight, tu.net_weight, pf.is_product_itself
            SQL,
            ['{'.implode(',', $tradeUnits).'}']
        ))->keyBy('id');

        $checks = [
            'legacy_top'                => [],
            'weight_mismatch'           => [],
            'plastic_without_polymer'   => [],
            'product_itself_not_glass'  => [],
        ];
        foreach ($ranked as $rank => $sko) {
            $row = $sko['trade_unit_id'] ? $packaging->get($sko['trade_unit_id']) : null;
            $base = Arr::only($sko, ['code', 'name', 'trade_unit_slug', 'units']);

            if ($sko['status'] === 'legacy' && $rank < self::TOP_SKOS) {
                $checks['legacy_top'][] = [...$base, 'detail' => __('Number :rank by SKOs moved', ['rank' => $rank + 1])];
            }
            if (!$row) {
                continue;
            }
            $declared = (float)$row->gross_weight - (float)$row->net_weight;
            if ($row->net_weight > 0 && $declared > 0 && abs((float)$row->packaging_g - $declared) > max($declared * self::WEIGHT_TOLERANCE, self::WEIGHT_TOLERANCE_G)) {
                $checks['weight_mismatch'][] = [...$base, 'detail' => __('Packaging :packaging g, gross less net :declared g', ['packaging' => round((float)$row->packaging_g, 1), 'declared' => round($declared, 1)])];
            }
            if ($row->plastic_without_polymer_g > 0) {
                $checks['plastic_without_polymer'][] = [...$base, 'detail' => __(':grams g of plastic', ['grams' => round((float)$row->plastic_without_polymer_g, 1)])];
            }
            if ($row->is_product_itself && $row->other_than_glass) {
                $checks['product_itself_not_glass'][] = [...$base, 'detail' => $row->other_than_glass];
            }
        }

        return collect($checks)->map(fn (array $rows, string $key) => [
            'key'   => $key,
            'count' => count($rows),
            'rows'  => array_slice($rows, 0, self::CHECK_ROWS),
        ])->values()->all();
    }

    /**
     * Share of units whose packaging has components, legacy weights included.
     */
    private function coverage(Collection $skos): ?float
    {
        $units = $skos->sum('units');

        return $units > 0 ? round($skos->whereIn('status', ['legacy', 'complete'])->sum('units') / $units * 100, 1) : null;
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    private function skos(Organisation $organisation, Carbon $from, Carbon $to): Collection
    {
        $rows = DB::select(
            <<<'SQL'
            SELECT l.org_stock_id, os.code, os.name, l.trade_unit_id, tu.slug AS trade_unit_slug, tu.packaging_family_id, pf.code AS packaging_family_code, pf.source,
                (SELECT COUNT(*) FROM packaging_family_has_components c WHERE c.packaging_family_id = tu.packaging_family_id) AS components,
                SUM(l.sko_quantity) AS units,
                SUM(l.sko_quantity) FILTER (WHERE l.activity IN ('imported', 'bought_domestic', 'purchase_unknown_origin', 'packed_filled')) AS units_in,
                SUM(l.sko_quantity) FILTER (WHERE l.activity IN ('sold_domestic', 'exported', 'sale_unknown_country')) AS units_out
            FROM epr_flow_lines l
            LEFT JOIN org_stocks os ON os.id = l.org_stock_id
            LEFT JOIN trade_units tu ON tu.id = l.trade_unit_id
            LEFT JOIN packaging_families pf ON pf.id = tu.packaging_family_id
            WHERE l.organisation_id = ? AND l.date BETWEEN ? AND ?
            GROUP BY l.org_stock_id, os.code, os.name, l.trade_unit_id, tu.slug, tu.packaging_family_id, pf.code, pf.source
            SQL,
            [$organisation->id, $from->toDateString(), $to->toDateString()]
        );

        return collect($rows)->map(fn (object $row) => [
            'org_stock_id'          => $row->org_stock_id,
            'trade_unit_id'         => $row->trade_unit_id,
            'code'                  => $row->code,
            'name'                  => $row->name,
            'trade_unit_slug'       => $row->trade_unit_slug,
            'packaging_family_code' => $row->packaging_family_code,
            'status'                => match (true) {
                $row->trade_unit_id === null                              => 'no_trade_unit',
                $row->packaging_family_id === null                        => 'no_packaging',
                (int)$row->components === 0                               => 'no_components',
                $row->source === PackagingFamilySourceEnum::LEGACY_UK_2026->value => 'legacy',
                default                                                   => 'complete',
            },
            'units'     => (float)$row->units,
            'units_in'  => (float)$row->units_in,
            'units_out' => (float)$row->units_out,
        ]);
    }

    /**
     * @return list<array{activity: string, label: string, units: float, coverage: float|null}>
     */
    private function activities(Organisation $organisation, Carbon $from, Carbon $to): array
    {
        $rows = DB::select(
            <<<'SQL'
            SELECT l.activity, SUM(l.sko_quantity) AS units,
                SUM(l.sko_quantity) FILTER (WHERE EXISTS (SELECT 1 FROM packaging_family_has_components c WHERE c.packaging_family_id = tu.packaging_family_id)) AS covered
            FROM epr_flow_lines l
            LEFT JOIN trade_units tu ON tu.id = l.trade_unit_id
            WHERE l.organisation_id = ? AND l.date BETWEEN ? AND ?
            GROUP BY l.activity
            SQL,
            [$organisation->id, $from->toDateString(), $to->toDateString()]
        );

        $labels = EprActivityEnum::labels();

        return collect($rows)
            ->map(fn (object $row) => [
                'activity' => $row->activity,
                'label'    => $labels[$row->activity] ?? $row->activity,
                'units'    => (float)$row->units,
                'coverage' => $row->units > 0 ? round((float)$row->covered / (float)$row->units * 100, 1) : null,
            ])
            ->sortByDesc('units')
            ->values()
            ->all();
    }
}
