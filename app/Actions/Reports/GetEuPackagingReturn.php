<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 10 Oct 2026 18:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Reports;

use App\Enums\Goods\Packaging\EprSchemeEnum;
use App\Enums\Goods\Packaging\PackagingMaterialCategoryEnum;
use App\Enums\Goods\Packaging\PackagingPolymerEnum;
use App\Models\Goods\EprMaterialMapping;
use App\Models\SysAdmin\Organisation;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * The packaging an organisation in an EU country placed on its own market in a period, in the materials of that
 * country's scheme (Slovakia reports quarterly to its OZV): the sales packaging of what it dispatched to addresses in
 * its country, plus an estimate of the shipment packaging those parcels went out in, taken as the shipment packaging
 * it used in the period times the share of its parcels that stayed in the country. Goods sent to other countries fall
 * under their schemes and are left out. Plastics without a polymer take the scheme's general plastic line.
 */
class GetEuPackagingReturn
{
    use AsAction;

    public function scheme(Organisation $organisation): ?EprSchemeEnum
    {
        $scheme = EprSchemeEnum::tryFrom(mb_strtolower((string)$organisation->country?->code));

        return $scheme === EprSchemeEnum::UK ? null : $scheme;
    }

    /**
     * @return array<string, mixed>
     */
    public function handle(Organisation $organisation, Carbon $from, Carbon $to): array
    {
        $scheme   = $this->scheme($organisation);
        $current  = $this->materials($organisation, $scheme, $from, $to);
        $days     = (int)$from->diffInDays($to) + 1;
        $previous = $this->materials($organisation, $scheme, $from->copy()->subDays($days), $from->copy()->subDay());

        $rows = [];
        foreach (array_unique([...array_keys($current['kg']), ...array_keys($previous['kg'])]) as $key) {
            [$material, $subcategory] = explode('|', $key);
            $kg          = $current['kg'][$key] ?? 0.0;
            $previousKg  = isset($previous['kg'][$key]) ? $previous['kg'][$key] : ($previous['has_data'] ? 0.0 : null);
            $rows[]      = [
                'scheme_material'    => $material,
                'scheme_subcategory' => $subcategory ?: null,
                'sales_kg'           => round($current['sales'][$key] ?? 0.0, 1),
                'shipment_kg'        => round($current['shipment'][$key] ?? 0.0, 1),
                'kg'                 => round($kg, 1),
                'previous_kg'        => $previousKg === null ? null : round($previousKg, 1),
                'change'             => $previousKg ? round(($kg - $previousKg) / $previousKg * 100, 1) : null,
            ];
        }
        usort($rows, fn (array $a, array $b) => $b['kg'] <=> $a['kg']);

        return [
            'scheme'                  => $scheme?->value,
            'scheme_label'            => $scheme ? EprSchemeEnum::labels()[$scheme->value] : null,
            'from'                    => $from->toDateString(),
            'to'                      => $to->toDateString(),
            'has_data'                => $current['has_data'],
            'kg'                      => round(array_sum($current['kg']), 1),
            'previous_kg'             => $previous['has_data'] ? round(array_sum($previous['kg']), 1) : null,
            'rows'                    => $rows,
            'domestic_parcel_share'   => $current['domestic_parcel_share'],
            'plastic_without_polymer' => round($current['plastic_without_polymer'], 1),
            'units_without_weights'   => $current['units_without_weights'],
            'units'                   => $current['units'],
        ];
    }

    /**
     * @return array{kg: array<string, float>, sales: array<string, float>, shipment: array<string, float>, has_data: bool, domestic_parcel_share: float|null, plastic_without_polymer: float, units: float, units_without_weights: float}
     */
    private function materials(Organisation $organisation, ?EprSchemeEnum $scheme, Carbon $from, Carbon $to): array
    {
        $bindings = [$organisation->id, $from->toDateString(), $to->toDateString()];

        $weights = DB::select(
            <<<'SQL'
            SELECT l.activity, pc.material_category, pc.polymer, SUM(l.quantity * pc.weight_g * fhc.quantity_per_unit) / 1000 AS kg
            FROM epr_flow_lines l
            LEFT JOIN org_stocks os ON os.id = l.org_stock_id
            JOIN trade_units tu ON tu.id = l.trade_unit_id
            JOIN packaging_families pf ON pf.id = tu.packaging_family_id AND NOT pf.is_product_itself
            JOIN packaging_family_has_components fhc ON fhc.packaging_family_id = pf.id
            JOIN packaging_components pc ON pc.id = fhc.packaging_component_id
            WHERE l.organisation_id = ? AND l.date BETWEEN ? AND ?
              AND pc.weight_g > 0
              AND ((l.activity = 'sold_domestic' AND NOT COALESCE(os.is_shipment_packaging, FALSE))
                OR (l.activity = 'shipment_packaging' AND COALESCE(os.is_shipment_packaging, FALSE)))
            GROUP BY l.activity, pc.material_category, pc.polymer
            SQL,
            $bindings
        );

        $units = DB::selectOne(
            <<<'SQL'
            SELECT SUM(l.quantity) AS units,
                SUM(l.quantity) FILTER (WHERE NOT EXISTS (SELECT 1 FROM packaging_family_has_components c WHERE c.packaging_family_id = tu.packaging_family_id)) AS without_weights
            FROM epr_flow_lines l
            LEFT JOIN trade_units tu ON tu.id = l.trade_unit_id
            WHERE l.organisation_id = ? AND l.date BETWEEN ? AND ? AND l.activity = 'sold_domestic'
            SQL,
            $bindings
        );

        $parcels = DB::selectOne(
            <<<'SQL'
            SELECT COUNT(*) AS all_notes, COUNT(*) FILTER (WHERE dn.delivery_country_id = o.country_id) AS domestic
            FROM delivery_notes dn
            JOIN organisations o ON o.id = dn.organisation_id
            WHERE dn.warehouse_id IN (SELECT id FROM warehouses WHERE organisation_id = ?)
              AND dn.organisation_id = o.id AND dn.deleted_at IS NULL AND dn.state = 'dispatched'
              AND dn.dispatched_at >= ?::date AND dn.dispatched_at < ?::date + 1
            SQL,
            $bindings
        );
        $share = $parcels->all_notes > 0 ? $parcels->domestic / $parcels->all_notes : null;

        $result = ['kg' => [], 'sales' => [], 'shipment' => [], 'has_data' => $weights !== [], 'domestic_parcel_share' => $share === null ? null : round($share * 100, 1),
                   'plastic_without_polymer' => 0.0, 'units' => (float)$units->units, 'units_without_weights' => (float)$units->without_weights];
        if (!$scheme) {
            return $result;
        }

        $mappings = [];
        foreach ($weights as $row) {
            $kg = (float)$row->kg * ($row->activity === 'shipment_packaging' ? ($share ?? 0) : 1);
            if ($kg <= 0) {
                continue;
            }
            $mappingKey = $row->material_category.'|'.$row->polymer;
            $mappings[$mappingKey] ??= EprMaterialMapping::resolve($scheme, PackagingMaterialCategoryEnum::from($row->material_category), $row->polymer ? PackagingPolymerEnum::from($row->polymer) : null);
            $mapping = $mappings[$mappingKey];
            $key     = ($mapping?->scheme_material ?? $row->material_category).'|'.$mapping?->scheme_subcategory;

            $bucket                    = $row->activity === 'shipment_packaging' ? 'shipment' : 'sales';
            $result[$bucket][$key]     = ($result[$bucket][$key] ?? 0) + $kg;
            $result['kg'][$key]        = ($result['kg'][$key] ?? 0) + $kg;
            if ($row->material_category === 'plastic' && !$row->polymer) {
                $result['plastic_without_polymer'] += $kg;
            }
        }

        return $result;
    }
}
