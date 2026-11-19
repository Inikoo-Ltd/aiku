<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 19 Nov 2026, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Inventory\OrgStock;

use App\Enums\SysAdmin\Organisation\OrganisationTypeEnum;
use App\Models\Inventory\Warehouse;
use App\Models\SysAdmin\Organisation;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Laravel\Nightwatch\Facades\Nightwatch;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * HELP-4810. Learns how many litres of a packed box one SKO takes, from the parcels packers recorded
 * (weight and outer size of each box) on wholesale delivery notes. Product sizes in the catalogue are
 * too patchy for this, and a box fills by volume long before its weight limit (about 7 litres per kg).
 * Each delivery note's packed litres are shared between its lines by weight; a SKO needs three notes.
 * The 35th percentile is kept, not the median: a small order alone in a big box teaches its air as volume,
 * and on orders packed in 38 cm boxes the median counted one box too many on half of them.
 * A note whose parcels weigh far from its estimated weight is left out, as its parcels were not
 * recorded with care, and so is a box shared with another note of the same customer.
 * Per warehouse it also keeps the litres per kg and the packing weight uplift, the fallback for SKOs
 * not learnt yet.
 */
class LearnOrgStocksPackedVolume
{
    use AsAction;

    public string $commandSignature = 'org_stocks:learn_packed_volume {organisations?*}';

    private const int DAYS = 120;
    private const int MIN_SAMPLES = 3;
    private const int SHARED_BOX_DAYS = 1;
    private const float MIN_WEIGHED_RATIO = 0.5;
    private const float MAX_WEIGHED_RATIO = 2.0;
    private const float MIN_LITRES_PER_KG = 0.5;
    private const float MAX_LITRES_PER_KG = 60.0;
    private const int MIN_WAREHOUSE_NOTES = 200;
    private const float PERCENTILE = 0.35;

    private const string PACKED_NOTES = <<<'SQL'
        WITH candidates AS MATERIALIZED (
            SELECT dn.id, dn.warehouse_id, dn.customer_id, dn.dispatched_at, dn.estimated_weight, dn.parcels::jsonb AS raw_parcels,
                CASE WHEN jsonb_typeof(dn.parcels::jsonb) = 'array' THEN dn.parcels::jsonb ELSE '[]'::jsonb END AS parcels
            FROM delivery_notes dn
            WHERE dn.warehouse_id IN (SELECT warehouses.id FROM warehouses WHERE warehouses.organisation_id = :organisation_id)
                AND dn.deleted_at IS NULL
                AND dn.organisation_id = :organisation_id
                AND dn.state = 'dispatched'
                AND dn.shop_type IN ('b2b', 'external')
                AND dn.dispatched_at > now() - make_interval(days => :days)
                AND dn.estimated_weight > 0
        ), measured AS MATERIALIZED (
            SELECT candidates.* FROM candidates
            WHERE jsonb_array_length(candidates.parcels) > 0
                AND NOT EXISTS (
                    SELECT 1 FROM jsonb_array_elements(candidates.parcels) parcel
                    WHERE NOT CASE
                        WHEN jsonb_typeof(parcel->'weight') = 'number' AND jsonb_typeof(parcel->'dimensions') = 'array' THEN
                            CASE WHEN jsonb_array_length(parcel->'dimensions') = 3 THEN
                                jsonb_typeof(parcel->'dimensions'->0) = 'number'
                                AND jsonb_typeof(parcel->'dimensions'->1) = 'number'
                                AND jsonb_typeof(parcel->'dimensions'->2) = 'number'
                            ELSE false END
                        ELSE false END
                )
                AND NOT EXISTS (
                    SELECT 1 FROM delivery_notes shared
                    WHERE shared.customer_id = candidates.customer_id
                        AND shared.id <> candidates.id
                        AND shared.state = 'dispatched'
                        AND shared.dispatched_at BETWEEN candidates.dispatched_at - make_interval(days => :shared_box_days) AND candidates.dispatched_at + make_interval(days => :shared_box_days)
                        AND shared.parcels::jsonb = candidates.raw_parcels
                )
        ), notes AS (
            SELECT measured.id, measured.warehouse_id, measured.estimated_weight,
                (SELECT sum((parcel->'dimensions'->>0)::numeric * (parcel->'dimensions'->>1)::numeric * (parcel->'dimensions'->>2)::numeric) / 1000
                    FROM jsonb_array_elements(measured.parcels) parcel) AS packed_litres,
                (SELECT sum((parcel->>'weight')::numeric) * 1000 FROM jsonb_array_elements(measured.parcels) parcel) AS parcels_weight
            FROM measured
        ), packed AS (
            SELECT * FROM notes
            WHERE packed_litres > 0
                AND round(parcels_weight) <> estimated_weight
                AND parcels_weight BETWEEN estimated_weight * CAST(:min_ratio AS numeric) AND estimated_weight * CAST(:max_ratio AS numeric)
                AND packed_litres * 1000 / estimated_weight BETWEEN CAST(:min_lpk AS numeric) AND CAST(:max_lpk AS numeric)
        )
        SQL;

    /**
     * @return array{learnt: int, warehouses: int}
     */
    public function handle(Organisation $organisation): array
    {
        $bindings = [
            'organisation_id' => $organisation->id,
            'days'            => self::DAYS,
            'shared_box_days' => self::SHARED_BOX_DAYS,
            'min_ratio'       => self::MIN_WEIGHED_RATIO,
            'max_ratio'       => self::MAX_WEIGHED_RATIO,
            'min_lpk'         => self::MIN_LITRES_PER_KG,
            'max_lpk'         => self::MAX_LITRES_PER_KG,
            'percentile'      => self::PERCENTILE,
        ];

        $learnt = DB::select(
            self::PACKED_NOTES.<<<'SQL'
            , lines AS (
                SELECT packed.id, packed.packed_litres, dni.org_stock_id, dni.quantity_dispatched,
                    dni.estimated_picked_weight AS line_weight,
                    sum(dni.estimated_picked_weight) OVER (PARTITION BY packed.id) AS note_weight
                FROM packed
                JOIN delivery_note_items dni ON dni.delivery_note_id = packed.id
                WHERE dni.quantity_dispatched > 0 AND dni.org_stock_id IS NOT NULL
            )
            SELECT org_stock_id, count(*) AS samples,
                percentile_cont(CAST(:percentile AS double precision)) WITHIN GROUP (ORDER BY packed_litres * line_weight / note_weight / quantity_dispatched) AS litres
            FROM lines
            WHERE note_weight > 0 AND line_weight > 0
            GROUP BY org_stock_id
            HAVING count(*) >= :min_samples
            SQL,
            [...$bindings, 'min_samples' => self::MIN_SAMPLES]
        );

        $warehouseStats = DB::select(
            self::PACKED_NOTES.<<<'SQL'
            SELECT warehouse_id, count(*) AS notes,
                percentile_cont(CAST(:percentile AS double precision)) WITHIN GROUP (ORDER BY packed_litres * 1000 / estimated_weight) AS litres_per_kg,
                percentile_cont(0.5) WITHIN GROUP (ORDER BY parcels_weight / estimated_weight) AS weight_uplift
            FROM packed
            GROUP BY warehouse_id
            HAVING count(*) >= :min_notes
            SQL,
            [...$bindings, 'min_notes' => self::MIN_WAREHOUSE_NOTES]
        );

        DB::transaction(function () use ($organisation, $learnt) {
            DB::table('org_stocks')
                ->where('organisation_id', $organisation->id)
                ->whereNotNull('packed_volume')
                ->update(['packed_volume' => null, 'packed_volume_samples' => 0]);

            foreach (array_chunk($learnt, 500) as $chunk) {
                $values = collect($chunk)->map(fn ($row) => sprintf('(%d, %F, %d)', $row->org_stock_id, round($row->litres, 4), $row->samples))->implode(',');
                DB::update(
                    "UPDATE org_stocks SET packed_volume = learnt.litres, packed_volume_samples = learnt.samples
                     FROM (VALUES $values) AS learnt(id, litres, samples)
                     WHERE org_stocks.id = learnt.id AND org_stocks.organisation_id = ?",
                    [$organisation->id]
                );
            }
        });

        foreach ($warehouseStats as $stats) {
            $warehouse = Warehouse::where('organisation_id', $organisation->id)->find($stats->warehouse_id);
            if (!$warehouse) {
                continue;
            }

            $data            = $warehouse->data ?? [];
            $data['packing'] = [
                'litres_per_kg' => round($stats->litres_per_kg, 2),
                'weight_uplift' => round($stats->weight_uplift, 3),
                'notes'         => $stats->notes,
                'learnt_at'     => now()->toIso8601String(),
            ];
            $warehouse->update(['data' => $data]);
        }

        return ['learnt' => count($learnt), 'warehouses' => count($warehouseStats)];
    }

    public function asCommand(Command $command): int
    {
        Nightwatch::dontSample();

        $organisations = Organisation::where('type', OrganisationTypeEnum::SHOP)
            ->when($command->argument('organisations'), fn ($query, $slugs) => $query->whereIn('slug', $slugs))
            ->get();

        foreach ($organisations as $organisation) {
            $stats = $this->handle($organisation);
            $command->info("$organisation->slug: {$stats['learnt']} SKOs learnt, {$stats['warehouses']} warehouses");
        }

        return 0;
    }
}
