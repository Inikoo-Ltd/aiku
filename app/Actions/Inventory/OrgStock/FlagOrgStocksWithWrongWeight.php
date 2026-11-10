<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 05 Nov 2026, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Inventory\OrgStock;

use App\Enums\SysAdmin\Organisation\OrganisationTypeEnum;
use App\Models\SysAdmin\Organisation;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Laravel\Nightwatch\Facades\Nightwatch;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * HELP-4540. Works out what a SKO really weighs from the parcels the warehouse weighed, and flags
 * the SKOs whose system weight (stocks.gross_weight, what every delivery note estimate multiplies)
 * is far from it. Only dispatched delivery notes holding a single SKO count, so the parcel weight
 * belongs to that SKO alone, less the box. A parcel whose weight equals the estimate was filled in
 * by UpdateDeliveryNoteStatePacked, not weighed, and is left out. HELP-4593: so is a parcel shared
 * with another delivery note of the same customer (identical parcels within a day): the box carried
 * that note too, so its weight is not this SKO's. Three parcels at least, so one bad weighing cannot
 * move the median.
 * The comparison is against the current system weight, so correcting the weight clears the flag
 * on the next run.
 */
class FlagOrgStocksWithWrongWeight
{
    use AsAction;

    public string $commandSignature = 'org_stocks:flag_wrong_weight {organisations?*}';

    private const int BOX_WEIGHT = 150;
    private const int DAYS = 180;
    private const int MIN_PARCELS = 3;
    private const int SHARED_BOX_DAYS = 1;
    private const int MIN_DIFFERENCE = 300;
    private const float MIN_RELATIVE_DIFFERENCE = 0.25;

    /**
     * @return array{measured: int, flagged: int}
     */
    public function handle(Organisation $organisation): array
    {
        $measurements = DB::select(
            <<<'SQL'
            WITH weighed AS (
                SELECT dn.id, dn.estimated_weight,
                    (SELECT sum((parcel->>'weight')::numeric) * 1000 FROM jsonb_array_elements(dn.parcels::jsonb) parcel) AS parcels_weight
                FROM delivery_notes dn
                WHERE dn.organisation_id = :organisation_id
                    AND dn.state = 'dispatched'
                    AND dn.dispatched_at > now() - make_interval(days => :days)
                    AND dn.estimated_weight > 0
                    AND jsonb_typeof(dn.parcels::jsonb) = 'array'
                    AND NOT EXISTS (
                        SELECT 1 FROM delivery_notes shared
                        WHERE shared.customer_id = dn.customer_id
                            AND shared.id <> dn.id
                            AND shared.state = 'dispatched'
                            AND shared.dispatched_at BETWEEN dn.dispatched_at - make_interval(days => :shared_box_days) AND dn.dispatched_at + make_interval(days => :shared_box_days)
                            AND shared.parcels::jsonb = dn.parcels::jsonb
                    )
            ), single_sko AS (
                SELECT min(dni.org_stock_id) AS org_stock_id, (weighed.parcels_weight - :box_weight) / sum(dni.quantity_dispatched) AS weight_per_sko
                FROM weighed
                JOIN delivery_note_items dni ON dni.delivery_note_id = weighed.id
                WHERE weighed.parcels_weight > 0 AND round(weighed.parcels_weight) <> weighed.estimated_weight
                GROUP BY weighed.id, weighed.parcels_weight
                HAVING count(DISTINCT dni.org_stock_id) = 1 AND sum(dni.quantity_dispatched) > 0
            )
            SELECT org_stock_id, count(*) AS parcels, round(percentile_cont(0.5) WITHIN GROUP (ORDER BY weight_per_sko))::int AS measured_weight
            FROM single_sko
            GROUP BY org_stock_id
            HAVING count(*) >= :min_parcels
            SQL,
            [
                'organisation_id' => $organisation->id,
                'days'            => self::DAYS,
                'box_weight'      => self::BOX_WEIGHT,
                'min_parcels'     => self::MIN_PARCELS,
                'shared_box_days' => self::SHARED_BOX_DAYS,
            ]
        );

        DB::table('org_stocks')
            ->where('organisation_id', $organisation->id)
            ->where(fn ($query) => $query->where('has_wrong_weight', true)->orWhereNotNull('measured_weight'))
            ->update(['has_wrong_weight' => false, 'measured_weight' => null, 'measured_weight_parcels' => 0]);

        $systemWeights = DB::table('org_stocks')
            ->join('stocks', 'stocks.id', 'org_stocks.stock_id')
            ->whereIn('org_stocks.id', array_column($measurements, 'org_stock_id'))
            ->pluck('stocks.gross_weight', 'org_stocks.id');

        $flagged = 0;
        foreach ($measurements as $measurement) {
            $measuredWeight = max(0, (int) $measurement->measured_weight);
            $hasWrongWeight = $this->isWrongWeight((int) ($systemWeights[$measurement->org_stock_id] ?? 0), $measuredWeight);
            $flagged        += (int) $hasWrongWeight;

            DB::table('org_stocks')->where('id', $measurement->org_stock_id)->update([
                'has_wrong_weight'        => $hasWrongWeight,
                'measured_weight'         => $measuredWeight,
                'measured_weight_parcels' => $measurement->parcels,
            ]);
        }

        return ['measured' => count($measurements), 'flagged' => $flagged];
    }

    public function isWrongWeight(int $systemWeight, int $measuredWeight): bool
    {
        $difference = abs($measuredWeight - $systemWeight);

        return $difference > self::MIN_DIFFERENCE && $difference > self::MIN_RELATIVE_DIFFERENCE * $systemWeight;
    }

    public function asCommand(Command $command): int
    {
        Nightwatch::dontSample();

        $organisations = Organisation::where('type', OrganisationTypeEnum::SHOP)
            ->when($command->argument('organisations'), fn ($query, $slugs) => $query->whereIn('slug', $slugs))
            ->get();

        foreach ($organisations as $organisation) {
            $stats = $this->handle($organisation);
            $command->info("$organisation->slug: {$stats['measured']} SKOs measured, {$stats['flagged']} with wrong weight");
        }

        return 0;
    }
}
