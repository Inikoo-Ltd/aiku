<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 31 Aug 2026 Malaga, Spain
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Procurement\OrgAgent;

use App\Actions\Helpers\CurrencyExchange\GetCurrencyExchange;
use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\WithProcurementAuthorisation;
use App\Enums\Procurement\ShoppingListItem\ShoppingListItemStateEnum;
use App\Models\Helpers\Tag;
use App\Models\Helpers\Currency;
use App\Models\Procurement\OrgAgent;
use App\Models\SysAdmin\Organisation;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Lorisleiva\Actions\ActionRequest;

class SuggestAgentShoppingList extends OrgAction
{
    use WithProcurementAuthorisation;

    /**
     * A deterministic proposal, not a guess: the forecaster's recommended quantity for each item
     * still short of stock in this bucket, rounded up to whole cartons and never below the
     * supplier's carton minimum, taken worst-cover first until the budget runs out.
     *
     * @return array{lines: array<int, array<string, mixed>>, budget: float, currency: string}
     */
    public function handle(OrgAgent $orgAgent, array $modelData): array
    {
        $capacity  = GetAgentOrderCapacity::run($orgAgent);
        $ceiling   = $capacity['agent_capacity']['lands_for_us_per_30d'];
        $budget    = (float) $modelData['budget'];

        if ($ceiling !== null) {
            $budget = min($budget, max(0, $ceiling - $capacity['list']['value']));
        }

        $spent = 0.0;
        $lines = [];

        foreach ($this->lines($orgAgent, $modelData['bucket'], $modelData['rank'] ?? null, $modelData['supplier_id'] ?? null) as $line) {
            if ($spent + $line['cost'] > $budget) {
                continue;
            }

            $spent  += $line['cost'];
            $lines[] = $line;
        }

        return [
            'lines'    => $lines,
            'budget'   => round($budget, 2),
            'currency' => $orgAgent->organisation->currency->code,
            'budget_left' => $ceiling === null ? null : round(max(0, $ceiling - $capacity['list']['value']), 2),
        ];
    }

    /**
     * Every item of the bucket still to be ordered, priced in the organisation's currency and
     * worst cover first, before any budget is applied. Items with no exchange rate are left out.
     *
     * @return array<int, array<string, mixed>>
     */
    public function lines(OrgAgent $orgAgent, string $bucket, ?string $rank = null, ?int $supplierId = null): array
    {
        $organisationCurrency = $orgAgent->organisation->currency;
        $agentLeadDays        = GetAgentLeadTimes::run($orgAgent)['agent']['days'];
        $rates                = [];
        $lines                = [];

        $candidates    = $this->candidates($orgAgent, $bucket, $rank, $supplierId);
        $cartonVolumes = $this->usualCartonVolumes($candidates->pluck('supplier_id')->unique()->all());

        foreach ($candidates as $candidate) {
            $cartonUnits = max(1, (int) ($candidate->units_per_carton ?: 1));
            $minCartons  = max(1, (int) ($candidate->minimum_carton_order ?: 1));
            $packedIn    = max(1, (int) ($candidate->packed_in ?: 1));

            if ($candidate->units_per_carton && $cartonUnits < $packedIn) {
                continue;
            }

            $neededUnits = (float) ($candidate->recommended_order_quantity ?: 0) * $packedIn;
            $cartons     = max($minCartons, (int) ceil($neededUnits / $cartonUnits));
            $units       = $cartons * $cartonUnits;

            $rates[$candidate->currency_id] ??= $candidate->currency_id
                ? GetCurrencyExchange::run(Currency::find($candidate->currency_id), $organisationCurrency)
                : null;

            $rate = $rates[$candidate->currency_id];

            if ($rate === null) {
                continue;
            }

            $lines[] = [
                'org_supplier_product_id' => (int) $candidate->id,
                'code'                    => $candidate->code,
                'name'                    => $candidate->name,
                'supplier_code'           => $candidate->supplier_code,
                'cartons'                 => $cartons,
                'units_per_carton'        => $cartonUnits,
                'quantity_units'          => $units,
                'cost'                    => round($units * (float) $candidate->cost * $rate, 2),
                'cbm'                     => round($cartons * (float) ($candidate->cbm ?: $cartonVolumes[$candidate->supplier_id] ?? $cartonVolumes[0] ?? 0), 4),
                'cbm_estimated'           => !$candidate->cbm,
                'days_of_cover'           => $candidate->days_of_cover !== null ? (int) $candidate->days_of_cover : null,
                'health_rank'             => $candidate->health_rank,
                'lead_time_days'          => (int) ($candidate->measured_lead_time_days ?? $candidate->estimated_lead_time_days ?? $agentLeadDays),
                'reason'                  => $this->reason($candidate, $cartons, $minCartons),
            ];
        }

        return $lines;
    }

    /**
     * A carton's volume where the supplier product does not record one: what a carton of that
     * supplier's other products takes on average, or of all these suppliers' products (key 0) when the
     * supplier records none. An approximation, flagged as such on every line that uses it.
     *
     * @param  array<int, int>  $supplierIds
     * @return array<int, float> supplier id => cubic metres per carton
     */
    private function usualCartonVolumes(array $supplierIds): array
    {
        if (!$supplierIds) {
            return [];
        }

        $recorded = DB::table('supplier_products')
            ->whereIn('supplier_id', $supplierIds)
            ->where('cbm', '>', 0)
            ->whereNull('deleted_at');

        $volumes = (clone $recorded)->groupBy('supplier_id')->selectRaw('supplier_id, avg(cbm) as cbm')->pluck('cbm', 'supplier_id')->map(fn ($cbm) => (float) $cbm)->all();

        return $volumes + [0 => (float) $recorded->avg('cbm')];
    }

    private function reason(object $candidate, int $cartons, int $minCartons): string
    {
        if ($cartons === $minCartons && $minCartons > 1) {
            return __("supplier's carton minimum");
        }

        if ($candidate->days_of_cover === null) {
            return __('never stocked, one carton to start');
        }

        return __(':days days of cover left', ['days' => (int) $candidate->days_of_cover]);
    }

    private function candidates(OrgAgent $orgAgent, string $bucket, ?string $rank, ?int $supplierId)
    {
        $ids = GetAgentStockCoverBuckets::make()->orgSupplierProductIdsInBucket($orgAgent, $bucket, $rank, $supplierId);

        if (!$ids) {
            return collect();
        }

        return DB::table('org_supplier_products as osp')
            ->join('supplier_products as sp', 'sp.id', 'osp.supplier_product_id')
            ->join('suppliers as sup', 'sup.id', 'sp.supplier_id')
            ->leftJoinLateral(GetAgentStockCoverBuckets::bestOrgStock(), 'os')
            ->leftJoin('org_stock_stats as s', 's.org_stock_id', 'os.id')
            ->whereIn('osp.id', $ids)
            ->whereRaw('coalesce(os.is_excluded_from_auto_ordering, false) = false')
            ->whereRaw('not '.Tag::outOfOrderingSeasonSql('os.stock_id'))
            ->whereRaw('not '.GetAgentStockCoverBuckets::onOpenPurchaseOrderExpression())
            ->whereRaw('coalesce(s.on_the_way_po_count, 0) = 0')
            ->whereNotExists(function ($query) {
                $query->selectRaw('1')
                    ->from('shopping_list_items as sli')
                    ->whereColumn('sli.org_supplier_product_id', 'osp.id')
                    ->where('sli.state', ShoppingListItemStateEnum::OPEN->value)
                    ->whereNull('sli.deleted_at');
            })
            ->orderByRaw('array_position(array['.implode(',', array_map(intval(...), $ids)).']::bigint[], osp.id)')
            ->select([
                'osp.id',
                'sp.code',
                'sp.name',
                'sp.cost',
                'sp.currency_id',
                'sp.units_per_carton',
                'sp.minimum_carton_order',
                'sp.cbm',
                'sp.supplier_id',
                'sp.measured_lead_time_days',
                'sp.estimated_lead_time_days',
                'sup.code as supplier_code',
                'os.health_rank',
                'os.packed_in',
                's.recommended_order_quantity',
                's.days_of_cover',
            ])
            ->get();
    }

    public function rules(): array
    {
        return [
            'budget'      => ['required', 'numeric', 'min:1'],
            'bucket'      => ['required', Rule::in(array_keys(GetAgentStockCoverBuckets::BUCKETS))],
            'rank'        => ['sometimes', 'nullable', Rule::in(['A', 'B', 'C', 'D', 'Z'])],
            'supplier_id' => ['sometimes', 'nullable', 'integer'],
        ];
    }

    public function asController(Organisation $organisation, OrgAgent $orgAgent, ActionRequest $request): array
    {
        $this->initialisation($organisation, $request);

        return $this->handle($orgAgent, $this->validatedData);
    }
}
