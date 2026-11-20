<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 20 Nov 2026 Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Procurement\OrgSupplier;

use App\Actions\OrgAction;
use App\Actions\Procurement\GetOrganisationStockCoverBuckets;
use App\Actions\Procurement\PurchaseOrder\CalculatePurchaseOrderTotalAmounts;
use App\Actions\Procurement\PurchaseOrder\Hydrators\PurchaseOrderHydrateTransactions;
use App\Actions\Procurement\PurchaseOrder\StorePurchaseOrder;
use App\Actions\Procurement\PurchaseOrderTransaction\StorePurchaseOrderTransaction;
use App\Actions\Traits\Authorisations\WithProcurementEditAuthorisation;
use App\Enums\Inventory\OrgStock\OrgStockStateEnum;
use App\Enums\Procurement\PurchaseOrder\PurchaseOrderStateEnum;
use App\Enums\Procurement\PurchaseOrderTransaction\PurchaseOrderTransactionStateEnum;
use App\Models\Helpers\Tag;
use App\Models\Procurement\OrgSupplier;
use App\Models\Procurement\OrgSupplierProduct;
use App\Models\Procurement\PurchaseOrder;
use App\Models\SysAdmin\Organisation;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Validation\ValidationException;
use Lorisleiva\Actions\ActionRequest;

class PrepareSupplierPurchaseOrder extends OrgAction
{
    use WithProcurementEditAuthorisation;

    public const array ORDER_BUCKETS = ['out', 'w1', 'w2'];

    private const int MAX_COVER_DAYS = 730;

    private const int MAXIMUM_CARTON_COVER_DAYS = 365;

    /**
     * Ordering from an independent supplier, one click, as from the manufacturing hub: what we are
     * out of, doomed or critical on goes onto the supplier's draft purchase order, worst first, in the
     * quantity each line's own Suggested chip shows on the order. What is already on an open order or on
     * a delivery not put away yet counts as coming and lowers the quantity; a SKO on any of our drafts
     * is left alone, so it can be run again.
     *
     * @throws ValidationException
     */
    public function handle(OrgSupplier $orgSupplier): PurchaseOrder
    {
        $fail = fn (string $message) => throw ValidationException::withMessages(['prepare' => $message]);

        return DB::transaction(function () use ($orgSupplier, $fail) {
            OrgSupplier::whereKey($orgSupplier->id)->lockForUpdate()->first();

            $lines = $this->lines($orgSupplier);
            if (!$lines) {
                $fail(__('Nothing to order from :supplier right now', ['supplier' => $orgSupplier->supplier->name]));
            }

            $purchaseOrder = PurchaseOrder::where('parent_type', 'OrgSupplier')
                ->where('parent_id', $orgSupplier->id)
                ->where('state', PurchaseOrderStateEnum::IN_PROCESS)
                ->latest('id')
                ->first() ?? StorePurchaseOrder::make()->action($orgSupplier, []);

            $orgSupplierProducts = OrgSupplierProduct::whereIn('id', array_column($lines, 'org_supplier_product_id'))->get()->keyBy('id');

            $storeTransaction          = StorePurchaseOrderTransaction::make();
            $storeTransaction->batched = true;
            $added                     = 0;

            foreach ($lines as $line) {
                try {
                    $storeTransaction->addOrgSupplierProduct($purchaseOrder, $orgSupplierProducts->get($line['org_supplier_product_id']), ['quantity_ordered' => $line['quantity_units']]);
                    $added++;
                } catch (ValidationException) {
                    continue;
                }
            }

            if ($added === 0) {
                $fail(__('Nothing to order from :supplier right now', ['supplier' => $orgSupplier->supplier->name]));
            }

            CalculatePurchaseOrderTotalAmounts::run($purchaseOrder);
            PurchaseOrderHydrateTransactions::run($purchaseOrder);

            return $purchaseOrder;
        });
    }

    /**
     * Every line Prepare order would add, worst first.
     *
     * @return array<int, array{org_supplier_product_id: int, bucket: string, skos: float, quantity_units: float, cost: float}>
     */
    public function lines(OrgSupplier $orgSupplier): array
    {
        $buckets  = GetSupplierStockCoverBuckets::make();
        $leadDays = GetSupplierLeadTime::run($orgSupplier)['days'];
        $lead     = $buckets->leadDaysExpression($leadDays);

        $rows = $buckets->scopedQuery($orgSupplier)
            ->joinSub($buckets->bucketsQuery($orgSupplier), 'cover', 'cover.id', 'p.id')
            ->whereIn('cover.bucket', self::ORDER_BUCKETS)
            ->where('os.state', OrgStockStateEnum::ACTIVE->value)
            ->where('sp.cost', '>', 0)
            ->whereRaw('coalesce(os.is_excluded_from_auto_ordering, false) = false')
            ->whereRaw('not '.Tag::outOfOrderingSeasonSql('os.stock_id', $lead))
            ->whereRaw('not '.$this->onAnyDraftExpression($orgSupplier->organisation_id))
            ->selectRaw("p.id, cover.bucket, os.health_rank, sp.cost, sp.units_per_carton,
                greatest(coalesce(os.packed_in, 1), 1) as packed_in,
                os.quantity_in_locations, os.quantity_available,
                s.predicted_daily_usage, s.days_of_cover, s.forecast_source, s.demand_forecast,
                $lead as lead_days,
                ".GetOrganisationStockCoverBuckets::make()->overstockDaysExpression().' as overstock_days,
                '.GetOrganisationStockCoverBuckets::comingSkosExpression('os').' as incoming,
                '.$this->largestRecentOrderExpression().' as largest_recent_order')
            ->orderByRaw("array_position(array['".implode("', '", self::ORDER_BUCKETS)."'], cover.bucket::text)")
            ->orderByRaw("case os.health_rank when 'A' then 1 when 'B' then 2 when 'C' then 3 when 'D' then 4 when 'Z' then 5 end nulls last")
            ->orderByRaw('s.days_of_cover nulls first')
            ->get();

        $lines = [];
        foreach ($rows as $row) {
            $skos = $this->suggestedSkos($row);
            if ($skos <= 0) {
                continue;
            }

            $units = $skos * (int) $row->packed_in;
            if ($row->largest_recent_order !== null && $units > (float) $row->largest_recent_order) {
                $units = (float) $row->largest_recent_order;
                $skos  = $units / (int) $row->packed_in;
            }

            $lines[] = [
                'org_supplier_product_id' => (int) $row->id,
                'bucket'                  => $row->bucket,
                'skos'                    => $skos,
                'quantity_units'          => $units,
                'cost'                    => round($units * (float) $row->cost, 2),
            ];
        }

        return $lines;
    }

    /**
     * What Prepare order would add from each of its stock groups, read from lines().
     *
     * @return array{lines: int, cost: float, buckets: array<string, array{lines: int, cost: float}>}
     */
    public function summary(OrgSupplier $orgSupplier): array
    {
        $lines = collect($this->lines($orgSupplier));

        return [
            'lines'   => $lines->count(),
            'cost'    => round($lines->sum('cost'), 2),
            'buckets' => collect(self::ORDER_BUCKETS)->mapWithKeys(fn (string $bucket) => [$bucket => [
                'lines' => $lines->where('bucket', $bucket)->count(),
                'cost'  => round($lines->where('bucket', $bucket)->sum('cost'), 2),
            ]])->all(),
        ];
    }

    /**
     * The SKOs the Suggested chip on a purchase order line shows (usePurchaseOrderStockCover.ts):
     * what the forecast takes until the lead time plus a month (or twice the lead time), less what is
     * on the shelf and coming, in whole cartons, one carton fewer when the whole ones pass the
     * overstock edge and one fewer still lasts the lead time and lands nearer the target. Unlike the
     * chip, a whole carton that would take more than a year to use up is not forced: an independent
     * supplier sells less than a carton (a 25 kg drum of an oil used 1 kg a year), as from a partner.
     * ponytail: ported from the chip; change both together.
     */
    public function suggestedSkos(object $row): float
    {
        $weeks = $row->forecast_source === 'timesfm'
            ? array_map(fn ($week) => max(0.0, (float) ($week[0] ?? 0)), (array) (json_decode((string) $row->demand_forecast, true)['weeks'] ?? []))
            : [];
        $dailyUsage = (float) ($row->predicted_daily_usage ?? 0);

        if ($dailyUsage <= 0 && !array_filter($weeks)) {
            return 0;
        }

        $leadDays      = (float) $row->lead_days ?: 14;
        $overstockDays = (float) $row->overstock_days ?: 120;
        $shelf         = max(0.0, (float) $row->quantity_in_locations);
        $stock         = $row->quantity_available === null ? $shelf : min($shelf, max(0.0, (float) $row->quantity_available));
        $onHand        = $stock + (float) $row->incoming;
        $targetDays    = min(max($leadDays + 30, 2 * $leadDays), max($leadDays + 14, $overstockDays - 7));

        $need = $this->demandOver($weeks, $dailyUsage, $targetDays) - $onHand;
        if ($need <= 0) {
            return 0;
        }

        $cartonSkos   = max(1.0, (float) ($row->units_per_carton ?: 1) / (int) $row->packed_in);
        $wholeCartons = ceil($need / $cartonSkos) * $cartonSkos;

        if ($this->coverDays($weeks, $dailyUsage, $wholeCartons) > self::MAXIMUM_CARTON_COVER_DAYS) {
            return ceil($need);
        }

        if ($this->coverDays($weeks, $dailyUsage, $onHand + $wholeCartons) > $overstockDays) {
            $oneCartonLess = $wholeCartons - $cartonSkos;
            $lessCoverDays = $oneCartonLess > 0 ? $this->coverDays($weeks, $dailyUsage, $onHand + $oneCartonLess) : 0;
            $overshootDays = $this->coverDays($weeks, $dailyUsage, $onHand + $wholeCartons) - $targetDays;
            if ($lessCoverDays >= $leadDays && $targetDays - $lessCoverDays < $overshootDays) {
                return $oneCartonLess;
            }
        }

        return $wholeCartons;
    }

    /**
     * @param  array<int, float>  $weeks
     */
    private function demandOver(array $weeks, float $dailyUsage, float $targetDays): float
    {
        $demand = 0.0;
        $days   = 0;
        foreach ($weeks as $week) {
            if ($days + 7 > $targetDays) {
                return $demand + $week * ($targetDays - $days) / 7;
            }
            $demand += $week;
            $days   += 7;
        }

        return $demand + $dailyUsage * max(0, $targetDays - $days);
    }

    /**
     * @param  array<int, float>  $weeks
     */
    private function coverDays(array $weeks, float $dailyUsage, float $amount): float
    {
        $left = $amount;
        $days = 0;
        foreach ($weeks as $week) {
            if ($week <= 0) {
                $days += 7;
                continue;
            }
            if ($left < $week) {
                return $days + $left / $week * 7;
            }
            $left -= $week;
            $days += 7;
        }

        return $dailyUsage <= 0 ? self::MAX_COVER_DAYS : min(self::MAX_COVER_DAYS, $days + $left / $dailyUsage);
    }

    /**
     * The most units of the SKO any of our orders asked for in a line over the last year. A line is
     * never bigger: the usage forecast of a production raw material can run several times what the
     * factory uses (RAWM-60 forecast 120 bags a day against about 8 received), and a buyer's own
     * orders are the sanity check. A SKO never ordered in the year is not capped.
     */
    private function largestRecentOrderExpression(): string
    {
        return "(select max(pot.quantity_ordered) from purchase_order_transactions pot
            join purchase_orders po on po.id = pot.purchase_order_id and po.deleted_at is null
            where pot.org_stock_id = os.id and pot.deleted_at is null
                and pot.state not in ('".PurchaseOrderTransactionStateEnum::CANCELLED->value."', '".PurchaseOrderTransactionStateEnum::NOT_RECEIVED->value."')
                and po.state not in ('".PurchaseOrderStateEnum::IN_PROCESS->value."', '".PurchaseOrderStateEnum::CANCELLED->value."', '".PurchaseOrderStateEnum::NOT_RECEIVED->value."')
                and po.created_at > now() - interval '1 year')";
    }

    private function onAnyDraftExpression(int $organisationId): string
    {
        return "exists (select 1 from purchase_order_transactions pot
            join purchase_orders po on po.id = pot.purchase_order_id and po.deleted_at is null
            where pot.org_stock_id = os.id and pot.deleted_at is null
                and pot.state not in ('".PurchaseOrderTransactionStateEnum::CANCELLED->value."', '".PurchaseOrderTransactionStateEnum::NOT_RECEIVED->value."')
                and po.organisation_id = $organisationId
                and po.state = '".PurchaseOrderStateEnum::IN_PROCESS->value."')";
    }

    public function asController(Organisation $organisation, OrgSupplier $orgSupplier, ActionRequest $request): PurchaseOrder
    {
        abort_if($orgSupplier->org_agent_id || $orgSupplier->organisation_id !== $organisation->id, 404);
        $this->initialisation($organisation, $request);

        return $this->handle($orgSupplier);
    }

    public function htmlResponse(PurchaseOrder $purchaseOrder, ActionRequest $request): RedirectResponse
    {
        return Redirect::route('grp.org.procurement.org_suppliers.show.purchase-orders.show', [
            $request->route('organisation')->slug,
            $request->route('orgSupplier')->slug,
            $purchaseOrder->slug,
        ]);
    }
}
