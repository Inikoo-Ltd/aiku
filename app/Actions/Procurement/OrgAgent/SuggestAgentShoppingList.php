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

        $candidates = $this->candidates(
            $orgAgent,
            $modelData['bucket'],
            $modelData['rank'] ?? null,
            $modelData['supplier_id'] ?? null
        );

        $organisationCurrency = $orgAgent->organisation->currency;
        $rates                = [];
        $spent                = 0.0;
        $lines                = [];

        foreach ($candidates as $candidate) {
            $cartonUnits = max(1, (int) ($candidate->units_per_carton ?: 1));
            $minCartons  = max(1, (int) ($candidate->minimum_carton_order ?: 1));
            $needed      = (float) ($candidate->recommended_order_quantity ?: 0);
            $cartons     = max($minCartons, (int) ceil($needed / $cartonUnits));
            $units       = $cartons * $cartonUnits;

            $rates[$candidate->currency_id] ??= $candidate->currency_id
                ? GetCurrencyExchange::run(Currency::find($candidate->currency_id), $organisationCurrency)
                : null;

            $rate = $rates[$candidate->currency_id];
            $cost = $rate === null ? null : round($units * (float) $candidate->cost * $rate, 2);

            if ($cost === null || $spent + $cost > $budget) {
                continue;
            }

            $spent  += $cost;
            $lines[] = [
                'org_supplier_product_id' => (int) $candidate->id,
                'code'                    => $candidate->code,
                'name'                    => $candidate->name,
                'supplier_code'           => $candidate->supplier_code,
                'cartons'                 => $cartons,
                'units_per_carton'        => $cartonUnits,
                'quantity_units'          => $units,
                'cost'                    => $cost,
                'days_of_cover'           => $candidate->days_of_cover !== null ? (int) $candidate->days_of_cover : null,
                'reason'                  => $this->reason($candidate, $cartons, $minCartons),
            ];
        }

        return [
            'lines'    => $lines,
            'budget'   => round($budget, 2),
            'currency' => $organisationCurrency->code,
        ];
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
                'sup.code as supplier_code',
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
