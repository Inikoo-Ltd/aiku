<?php

/*
    * Author: Vika Aqordi
    * Created on: 2026-11-27 10:00
    * Github: https://github.com/aqordeon
    * Copyright: 2026
*/

namespace App\Actions\SupplyChain\SupplierShareLink\UI;

use App\Actions\Procurement\OrgSupplier\PrepareSupplierPurchaseOrder;
use App\Enums\Procurement\SupplierOrderProposal\SupplierOrderProposalStateEnum;
use App\Enums\SysAdmin\Organisation\OrganisationTypeEnum;
use App\Models\Procurement\OrgSupplier;
use App\Models\Procurement\OrgSupplierProduct;
use App\Models\Procurement\SupplierOrderProposal;
use App\Models\SupplyChain\Supplier;
use App\Models\SupplyChain\SupplierProduct;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Lorisleiva\Actions\Concerns\AsObject;

/**
 * What the supplier can propose we order now, direct or through its agent: per organisation, the products it is out
 * of or will run out of before any new delivery can land (stock cover out and w1), in the quantity Prepare order
 * would put on the draft (PrepareSupplierPurchaseOrder::lines, so its gates apply: auto ordering, season, cost, a SKO
 * already on a draft). A line waiting for the buyer, or rejected in the last REJECTED_REST_DAYS, is not offered again.
 * The supplier sees the stock state, as on its Sales page, never our quantities.
 */
class GetSupplierPortalOrderNow
{
    use AsObject;

    public const array BUCKETS = ['out', 'w1'];

    public const int REJECTED_REST_DAYS = 14;

    public const int HISTORY_DAYS = 90;

    private const array INTERNAL_KEYS = ['org_supplier_id', 'organisation_id', 'supplier_product_id'];

    /**
     * @return array{currency: string|null, lines: list<array<string, mixed>>, proposals: list<array<string, mixed>>}
     */
    public function handle(Supplier $supplier): array
    {
        return [
            'currency'  => $supplier->currency?->code,
            'lines'     => $this->lines($supplier)->map(fn (array $line) => Arr::except($line, self::INTERNAL_KEYS))->values()->all(),
            'proposals' => $this->proposals($supplier),
        ];
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    public function lines(Supplier $supplier): Collection
    {
        $orgSuppliers = $supplier->orgSuppliers()->where('status', true)->with('organisation')->get()
            ->filter(fn (OrgSupplier $orgSupplier) => $orgSupplier->organisation->type !== OrganisationTypeEnum::AGENT);

        $resting = SupplierOrderProposal::where('supplier_id', $supplier->id)
            ->where(fn ($query) => $query->where('state', SupplierOrderProposalStateEnum::PENDING)
                ->orWhere(fn ($query) => $query->where('state', SupplierOrderProposalStateEnum::REJECTED)->where('decided_at', '>', now()->subDays(self::REJECTED_REST_DAYS))))
            ->pluck('org_supplier_product_id')
            ->flip();

        $rows = $orgSuppliers->flatMap(fn (OrgSupplier $orgSupplier) => collect(PrepareSupplierPurchaseOrder::make()->lines($orgSupplier))
            ->filter(fn (array $line) => in_array($line['bucket'], self::BUCKETS, true) && !$resting->has($line['org_supplier_product_id']))
            ->map(fn (array $line) => [...$line, 'org_supplier' => $orgSupplier]));

        if ($rows->isEmpty()) {
            return collect();
        }

        $orgSupplierProducts = OrgSupplierProduct::whereIn('id', $rows->pluck('org_supplier_product_id'))->pluck('supplier_product_id', 'id');
        $products            = SupplierProduct::whereIn('id', $orgSupplierProducts->values())->with(['image', 'tradeUnits.image'])->get()->keyBy('id');

        return $rows->map(function (array $row) use ($orgSupplierProducts, $products) {
            $product    = $products->get($orgSupplierProducts->get($row['org_supplier_product_id']));
            $imageOwner = $product?->image_id ? $product : $product?->tradeUnits->first(fn ($tradeUnit) => $tradeUnit->image_id !== null);
            $perCarton  = (int) ($product?->units_per_carton ?? 0);
            $units      = round((float) $row['quantity_units'], 3);

            return [
                'id'                  => $row['org_supplier_product_id'],
                'org_supplier_id'     => $row['org_supplier']->id,
                'organisation_id'     => $row['org_supplier']->organisation_id,
                'supplier_product_id' => $product?->id,
                'code'                => $product?->code,
                'name'                => $product?->name,
                'image'               => $imageOwner?->imageSources(80, 80),
                'organisation'        => $row['org_supplier']->organisation->name,
                'bucket'              => $row['bucket'],
                'quantity_units'      => $units,
                'units_per_carton'    => $perCarton ?: null,
                'unit_cost'           => (float) ($product?->cost ?? 0),
                'value'               => round((float) $row['cost'], 2),
            ];
        })->sortBy(fn (array $line) => [$line['organisation'], array_search($line['bucket'], self::BUCKETS, true), (string) $line['code']])->values();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function proposals(Supplier $supplier): array
    {
        return SupplierOrderProposal::where('supplier_id', $supplier->id)
            ->where('created_at', '>', now()->subDays(self::HISTORY_DAYS))
            ->with(['organisation', 'supplierProduct'])
            ->latest('id')
            ->limit(200)
            ->get()
            ->map(fn (SupplierOrderProposal $proposal) => [
                'id'                      => $proposal->id,
                'code'                    => $proposal->supplierProduct?->code,
                'name'                    => $proposal->supplierProduct?->name,
                'organisation'            => $proposal->organisation->name,
                'quantity_units'          => (float) $proposal->quantity_units,
                'accepted_quantity_units' => $proposal->accepted_quantity_units !== null ? (float) $proposal->accepted_quantity_units : null,
                'value'                   => round((float) ($proposal->accepted_quantity_units ?? $proposal->quantity_units) * (float) $proposal->unit_cost, 2),
                'state'                   => $proposal->state->value,
                'state_label'             => SupplierOrderProposalStateEnum::labels()[$proposal->state->value],
                'reject_reason'           => $proposal->reject_reason,
                'proposed_at'             => $proposal->created_at?->toIso8601String(),
                'decided_at'              => $proposal->decided_at?->toIso8601String(),
            ])
            ->all();
    }
}
