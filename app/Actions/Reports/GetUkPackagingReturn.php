<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 10 Oct 2026 15:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Reports;

use App\Models\Goods\EprMaterialMapping;
use App\Models\SysAdmin\Organisation;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * The UK packaging EPR return for a period, from the EPR flow lines and the packaging weights of what moved: kg by
 * activity, packaging type, class, material and RAM rating, as the Report Packaging Data file lays them out, next to
 * the previous period of the same length.
 *
 * Packaging is reported once: own-brand goods sold in the UK go under Brand owner, so their imports are left out of
 * Imported unless $countOwnBrandImports asks for the method used until 2026. Packaging that is itself the product
 * (candle jars) is never reported.
 */
class GetUkPackagingReturn
{
    use AsAction;

    public const array ACTIVITIES = [
        'SO' => 'Brand owner',
        'IM' => 'Imported',
        'PF' => 'Packed or filled',
    ];

    public const array CLASSES = [
        'primary'   => 'P1',
        'grouped'   => 'P2',
        'service'   => 'P3',
        'transport' => 'P4',
    ];

    public const array RAM = ['green' => 'G', 'amber' => 'A', 'red' => 'R'];

    public const float CHANGE_WARNING = 30.0;

    /**
     * @return array<string, mixed>
     */
    public function handle(Organisation $organisation, Carbon $from, Carbon $to, bool $countOwnBrandImports = false): array
    {
        $lines    = $this->lines($organisation, $from, $to, $countOwnBrandImports);
        $days     = (int)$from->diffInDays($to) + 1;
        $previous = $this->lines($organisation, $from->copy()->subDays($days), $from->copy()->subDay(), $countOwnBrandImports);

        $form         = $this->form($lines);
        $previousForm = $this->form($previous);
        $materials    = $lines->merge($previous)->pluck('material')->unique()->sort()->values();

        return [
            'from'              => $from->toDateString(),
            'to'                => $to->toDateString(),
            'submission_period' => $this->submissionPeriod($from, $to),
            'has_data'          => $lines->isNotEmpty(),
            'kg'                => round($lines->sum('kg')),
            'previous_kg'       => $previous->isNotEmpty() ? round($previous->sum('kg')) : null,
            'materials'         => $materials->all(),
            'form'              => collect(self::ACTIVITIES)->map(fn (string $label, string $code) => [
                'activity' => $code,
                'label'    => __($label),
                'cells'    => $materials->mapWithKeys(fn (string $material) => [
                    $material => $this->cell($form[$code][$material] ?? 0.0, $previous->isNotEmpty() ? ($previousForm[$code][$material] ?? 0.0) : null),
                ])->all(),
                ...$this->cell(array_sum($form[$code] ?? []), $previous->isNotEmpty() ? array_sum($previousForm[$code] ?? []) : null),
            ])->values()->all(),
            'lines' => $lines->sortBy(fn (array $line) => implode('|', [$line['activity'], $line['type'], $line['class'], $line['material'], $line['ram']]))->values()->all(),
        ];
    }

    /**
     * Large producers report January to June as P1 and July to December as P4.
     */
    public function submissionPeriod(Carbon $from, Carbon $to): ?string
    {
        if ($from->year !== $to->year || $from->day !== 1 || !$to->isLastOfMonth()) {
            return null;
        }

        return match ([$from->month, $to->month]) {
            [1, 6]  => "$from->year-P1",
            [7, 12] => "$from->year-P4",
            default => null,
        };
    }

    /**
     * @return array{kg: float, previous_kg: float|null, change: float|null, warning: bool}
     */
    private function cell(float $kg, ?float $previousKg): array
    {
        $change = $previousKg ? round(($kg - $previousKg) / $previousKg * 100, 1) : null;

        return [
            'kg'          => round($kg),
            'previous_kg' => $previousKg === null ? null : round($previousKg),
            'change'      => $change,
            'warning'     => $change !== null && abs($change) > self::CHANGE_WARNING,
        ];
    }

    /**
     * @return array<string, array<string, float>>
     */
    private function form(Collection $lines): array
    {
        $form = [];
        foreach ($lines as $line) {
            $form[$line['activity']][$line['material']] = ($form[$line['activity']][$line['material']] ?? 0) + $line['kg'];
        }

        return $form;
    }

    /**
     * @return Collection<int, array{activity: string, type: string, class: string, material: string, ram: string|null, kg: float}>
     */
    private function lines(Organisation $organisation, Carbon $from, Carbon $to, bool $countOwnBrandImports): Collection
    {
        $rows = DB::select(
            <<<'SQL'
            SELECT l.activity, pf.brand_ownership, pf.end_use, pc.packaging_level, pc.material_category, pc.ram_rating, pc.is_beverage_container,
                SUM(l.quantity * pc.weight_g * fhc.quantity_per_unit) / 1000 AS kg
            FROM epr_flow_lines l
            JOIN trade_units tu ON tu.id = l.trade_unit_id
            JOIN packaging_families pf ON pf.id = tu.packaging_family_id AND NOT pf.is_product_itself
            JOIN packaging_family_has_components fhc ON fhc.packaging_family_id = pf.id
            JOIN packaging_components pc ON pc.id = fhc.packaging_component_id
            WHERE l.organisation_id = ? AND l.date BETWEEN ? AND ?
              AND l.activity IN ('sold_domestic', 'imported', 'packed_filled')
              AND pc.weight_g > 0
            GROUP BY l.activity, pf.brand_ownership, pf.end_use, pc.packaging_level, pc.material_category, pc.ram_rating, pc.is_beverage_container
            SQL,
            [$organisation->id, $from->toDateString(), $to->toDateString()]
        );

        $materials = EprMaterialMapping::where('scheme', 'uk')->whereNull('polymer')->get()->keyBy(fn (EprMaterialMapping $mapping) => $mapping->material_category->value);

        $lines = [];
        foreach ($rows as $row) {
            $ownBrand = $row->brand_ownership === 'own_brand';
            $activity = match ($row->activity) {
                'sold_domestic' => $ownBrand ? 'SO' : null,
                'imported'      => $ownBrand && !$countOwnBrandImports ? null : 'IM',
                'packed_filled' => $ownBrand ? null : 'PF',
            };
            $class = self::CLASSES[$row->packaging_level] ?? null;
            if (!$activity || !$class) {
                continue;
            }

            $type = match (true) {
                $row->is_beverage_container => $row->end_use === 'non_household' ? 'NDC' : 'HDC',
                $row->end_use === 'non_household' => 'NH',
                default => 'HH',
            };
            $mapping  = $materials->get($row->material_category);
            $material = $mapping?->scheme_code ?? 'OT';
            $ram      = in_array($type, ['HH', 'HDC']) ? (self::RAM[$row->ram_rating] ?? self::RAM[$mapping?->default_ram_rating?->value] ?? 'R') : null;

            $key            = implode('|', [$activity, $type, $class, $material, $ram]);
            $lines[$key] ??= ['activity' => $activity, 'type' => $type, 'class' => $class, 'material' => $material, 'ram' => $ram, 'kg' => 0.0];
            $lines[$key]['kg'] += (float)$row->kg;
        }

        return collect(array_values($lines));
    }
}
