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
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Which SKOs moved in a period without packaging weights an EPR return can use, ranked by the units they carried,
 * so the people keeping packaging data fix what weighs most on the return first.
 */
class GetEprPackagingCompleteness
{
    use AsAction;

    public const int ROWS = 300;

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
            'rows'       => $toFix->take(self::ROWS)->map(fn (array $sko) => [
                ...$sko,
                'share' => $units > 0 ? round($sko['units'] / $units * 100, 2) : 0,
            ])->all(),
            'rows_total' => $toFix->count(),
        ];
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
                SUM(l.quantity) AS units,
                SUM(l.quantity) FILTER (WHERE l.activity IN ('imported', 'bought_domestic', 'purchase_unknown_origin', 'packed_filled')) AS units_in,
                SUM(l.quantity) FILTER (WHERE l.activity IN ('sold_domestic', 'exported', 'sale_unknown_country')) AS units_out
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
            SELECT l.activity, SUM(l.quantity) AS units,
                SUM(l.quantity) FILTER (WHERE EXISTS (SELECT 1 FROM packaging_family_has_components c WHERE c.packaging_family_id = tu.packaging_family_id)) AS covered
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
