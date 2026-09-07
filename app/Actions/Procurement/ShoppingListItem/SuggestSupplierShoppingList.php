<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 31 Aug 2026 Malaga, Spain
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Procurement\ShoppingListItem;

use App\Actions\OrgAction;
use App\Actions\Procurement\OrgSupplier\GetSupplierOrderCapacity;
use App\Actions\Procurement\OrgSupplier\GetSupplierStockCoverBuckets;
use App\Enums\Catalogue\HealthRankEnum;
use App\Enums\Procurement\ShoppingListItem\ShoppingListItemStateEnum;
use App\Models\Procurement\OrgSupplier;
use App\Models\SysAdmin\Organisation;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Lorisleiva\Actions\ActionRequest;

class SuggestSupplierShoppingList extends OrgAction
{
    public function authorize(ActionRequest $request): bool
    {
        if ($this->asAction) {
            return true;
        }

        return $request->user()->authTo("procurement.{$this->organisation->id}.edit");
    }

    /**
     * Deterministic fill: the forecaster's recommended quantity, rounded up to whole cartons,
     * tightest cover first, until the budget or the supplier's cap runs out. No guessing.
     *
     * @return array{currency: string, budget: float, total: float, lines: array<int, array<string, mixed>>}
     */
    public function handle(OrgSupplier $orgSupplier, float $budget, ?string $bucket = null, ?string $rank = null): array
    {
        $candidates = $this->candidates($orgSupplier, $bucket, $rank);
        $lines      = $this->respectSupplierCap($orgSupplier, $this->greedyFill($candidates, $budget));

        return [
            'currency' => $orgSupplier->supplier->currency->code,
            'budget'   => $budget,
            'total'    => round(array_sum(array_column($lines, 'cost')), 2),
            'lines'    => $lines,
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    protected function candidates(OrgSupplier $orgSupplier, ?string $bucket, ?string $rank): array
    {
        $query = GetSupplierStockCoverBuckets::make()->scopedQuery($orgSupplier)
            ->whereRaw("not exists (select 1 from shopping_list_items sli
                where sli.org_supplier_product_id = p.id
                    and sli.state = '".ShoppingListItemStateEnum::OPEN->value."'
                    and sli.deleted_at is null)")
            ->where('sp.cost', '>', 0)
            ->select([
                'p.id',
                'sp.code',
                'sp.name',
                'sp.cost',
                'sp.units_per_carton',
                'sp.minimum_carton_order',
                'os.id as org_stock_id',
                'os.quantity_available as our_stock',
                'os.health_rank',
                's.days_of_cover',
                's.predicted_daily_usage',
                's.recommended_order_quantity',
            ]);

        if ($bucket) {
            $scoped = GetSupplierStockCoverBuckets::make()->orgSupplierProductIdsInBucket($orgSupplier, $bucket, $rank);
            $query->whereIn('p.id', $scoped);
        }

        return $query->get()->map(fn ($row) => [
            'org_supplier_product_id' => $row->id,
            'code'                    => $row->code,
            'name'                    => $row->name,
            'cost'                    => (float) $row->cost,
            'units_per_carton'        => (int) ($row->units_per_carton ?? 0),
            'minimum_carton_order'    => $row->minimum_carton_order !== null ? (int) $row->minimum_carton_order : null,
            'never_stocked'           => $row->org_stock_id === null,
            'health_rank'             => $row->health_rank,
            'cap_exempt'              => ($row->org_stock_id !== null && (float) $row->our_stock <= 0)
                || $row->health_rank === HealthRankEnum::A->value,
            'our_stock'               => (float) ($row->our_stock ?? 0),
            'days_of_cover'           => $row->days_of_cover !== null ? (float) $row->days_of_cover : null,
            'recommended'             => $row->recommended_order_quantity !== null ? (float) $row->recommended_order_quantity : null,
            'quarterly_usage'         => round((float) ($row->predicted_daily_usage ?? 0) * 91, 1),
        ])->all();
    }

    protected function rankPriority(?string $rank): int
    {
        return match ($rank) {
            'A' => 0,
            'B' => 1,
            'C' => 2,
            'D' => 4,
            'Z' => 5,
            default => 3,
        };
    }

    /**
     * @param array<int, array<string, mixed>> $candidates
     *
     * @return array<int, array<string, mixed>>
     */
    protected function greedyFill(array $candidates, float $budget): array
    {
        $ranked = collect($candidates)
            ->filter(fn ($candidate) => $candidate['recommended'] > 0 || $candidate['quarterly_usage'] > 0)
            ->sortBy([
                fn ($a, $b) => $this->rankPriority($a['health_rank']) <=> $this->rankPriority($b['health_rank']),
                fn ($a, $b) => ($a['days_of_cover'] ?? PHP_INT_MAX) <=> ($b['days_of_cover'] ?? PHP_INT_MAX),
            ]);

        $lines     = [];
        $remaining = $budget;

        foreach ($ranked as $candidate) {
            $target = $candidate['recommended'] !== null && $candidate['recommended'] > 0
                ? ceil($candidate['recommended'])
                : max(0.0, ceil($candidate['quarterly_usage'] - $candidate['our_stock']));

            $quantity = $this->roundToCarton($target, $candidate['units_per_carton']);
            $quantity = min($quantity, floor($remaining / $candidate['cost']));
            $quantity = $this->roundDownToCarton($quantity, $candidate['units_per_carton']);

            if ($quantity < 1) {
                continue;
            }

            $cost       = round($quantity * $candidate['cost'], 2);
            $remaining -= $cost;

            $lines[] = [
                'org_supplier_product_id' => $candidate['org_supplier_product_id'],
                'code'                    => $candidate['code'],
                'name'                    => $candidate['name'],
                'quantity'                => $quantity,
                'cost_per_unit'           => $candidate['cost'],
                'cost'                    => $cost,
                'cartons'                 => $candidate['units_per_carton'] > 0
                    ? round($quantity / $candidate['units_per_carton'], 2)
                    : null,
                'minimum_carton_order'    => $candidate['minimum_carton_order'],
                'reason'                  => $this->reason($candidate),
            ];

            if ($remaining < 1) {
                break;
            }
        }

        return $lines;
    }

    protected function roundToCarton(float $quantity, int $unitsPerCarton): float
    {
        return $unitsPerCarton > 0 ? ceil($quantity / $unitsPerCarton) * $unitsPerCarton : ceil($quantity);
    }

    protected function roundDownToCarton(float $quantity, int $unitsPerCarton): float
    {
        return $unitsPerCarton > 0 ? floor($quantity / $unitsPerCarton) * $unitsPerCarton : floor($quantity);
    }

    /**
     * @param array<string, mixed> $candidate
     */
    protected function reason(array $candidate): string
    {
        $reason = sprintf(
            'Our sales/quarter ~%d · our stock %d',
            (int) round($candidate['quarterly_usage']),
            (int) floor($candidate['our_stock'])
        );

        if ($candidate['never_stocked']) {
            return $reason.' · never stocked';
        }

        if ($candidate['days_of_cover'] !== null) {
            $reason .= $candidate['days_of_cover'] <= 0
                ? ' · we run out now'
                : sprintf(' · we run out in ~%d days', (int) round($candidate['days_of_cover']));
        }

        return $reason;
    }

    /**
     * @param array<int, array<string, mixed>> $lines
     *
     * @return array<int, array<string, mixed>>
     */
    protected function respectSupplierCap(OrgSupplier $orgSupplier, array $lines): array
    {
        $capacity  = GetSupplierOrderCapacity::run($orgSupplier);
        $cap       = $capacity['supplier_capacity']['delivers_to_us_per_30d'];
        $shareLeft = $capacity['blocked']['warehouse_full']
            ? 0
            : max(0, $capacity['warehouse']['supplier_share_limit'] - $capacity['warehouse']['supplier_share_used']);

        $neverStocked = DB::table('org_supplier_products as p')
            ->whereIn('p.id', array_column($lines, 'org_supplier_product_id'))
            ->whereNotExists(function ($query) {
                $query->selectRaw('1')
                    ->from('org_stock_has_org_supplier_products as link')
                    ->whereColumn('link.org_supplier_product_id', 'p.id')
                    ->where('link.status', true);
            })
            ->pluck('p.id')
            ->flip();

        $projected = $capacity['list']['value'];
        $kept      = [];

        foreach ($lines as $line) {
            if ($neverStocked->has($line['org_supplier_product_id'])) {
                if ($shareLeft <= 0) {
                    continue;
                }
                $shareLeft--;
            }

            if ($cap !== null && $projected >= $cap) {
                continue;
            }

            $projected += $line['cost'];
            $kept[]     = $line;
        }

        return $kept;
    }

    public function rules(): array
    {
        return [
            'budget' => ['required', 'numeric', 'min:1'],
            'bucket' => ['sometimes', 'nullable', Rule::in(array_keys(GetSupplierStockCoverBuckets::BUCKETS))],
            'rank'   => ['sometimes', 'nullable', Rule::in(['A', 'B', 'C', 'D', 'Z'])],
        ];
    }

    public function asController(Organisation $organisation, OrgSupplier $orgSupplier, ActionRequest $request): array
    {
        abort_if($orgSupplier->org_agent_id, 404);

        $this->initialisation($organisation, $request);

        return $this->handle(
            $orgSupplier,
            (float) $this->validatedData['budget'],
            $this->validatedData['bucket'] ?? null,
            $this->validatedData['rank'] ?? null
        );
    }

    public function action(OrgSupplier $orgSupplier, array $modelData): array
    {
        $this->asAction = true;
        $this->initialisation($orgSupplier->organisation, $modelData);

        return $this->handle(
            $orgSupplier,
            (float) $this->validatedData['budget'],
            $this->validatedData['bucket'] ?? null,
            $this->validatedData['rank'] ?? null
        );
    }
}
