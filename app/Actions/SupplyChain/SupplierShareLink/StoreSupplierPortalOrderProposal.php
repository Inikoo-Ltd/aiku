<?php

/*
    * Author: Vika Aqordi
    * Created on: 2026-11-27 10:00
    * Github: https://github.com/aqordeon
    * Copyright: 2026
*/

namespace App\Actions\SupplyChain\SupplierShareLink;

use App\Actions\SupplyChain\SupplierShareLink\UI\GetSupplierPortalOrderNow;
use App\Enums\Procurement\SupplierOrderProposal\SupplierOrderProposalStateEnum;
use App\Models\Procurement\SupplierOrderProposal;
use App\Models\SupplyChain\Supplier;
use App\Models\SupplyChain\SupplierShareLink;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * The supplier proposes, from its portal, lines of its Propose an order tab: only a line still offered there can be proposed,
 * in any quantity above zero: the buyer settles the quantity on accepting. Each line waits for the buyer of its organisation,
 * who is told once per organisation.
 */
class StoreSupplierPortalOrderProposal
{
    use AsAction;

    public const int MAX_LINES = 200;

    /**
     * @param  list<array{id: int, quantity_units: float}>  $lines
     *
     * @return Collection<int, SupplierOrderProposal>
     *
     * @throws ValidationException
     */
    public function handle(SupplierShareLink $link, array $lines, ?string $note): Collection
    {
        $supplier  = $link->supplier;
        $proposals = DB::transaction(function () use ($supplier, $link, $lines, $note) {
            Supplier::whereKey($supplier->id)->lockForUpdate()->first();

            return $this->store($supplier, $link, $lines, $note);
        });

        $proposals->groupBy('org_supplier_id')->each(fn (Collection $proposalsOfOrgSupplier) => NotifyPurchasingOfSupplierOrderProposal::run($proposalsOfOrgSupplier));

        return $proposals;
    }

    /**
     * @param  list<array{id: int, quantity_units: float}>  $lines
     *
     * @return Collection<int, SupplierOrderProposal>
     *
     * @throws ValidationException
     */
    private function store(Supplier $supplier, SupplierShareLink $link, array $lines, ?string $note): Collection
    {
        $offered = GetSupplierPortalOrderNow::make()->lines($supplier)->keyBy('id');

        $chosen = collect($lines)->keyBy('id')->filter(fn (array $line, int $id) => $offered->has($id));
        if ($chosen->isEmpty()) {
            throw ValidationException::withMessages(['lines' => __('These products no longer need ordering. Reload the page to see what we need now.')]);
        }

        return $chosen->map(function (array $line, int $id) use ($offered, $supplier, $link, $note) {
            $offeredLine = $offered->get($id);

            return SupplierOrderProposal::create([
                'group_id'                   => $supplier->group_id,
                'organisation_id'            => $offeredLine['organisation_id'],
                'supplier_id'                => $supplier->id,
                'org_supplier_id'            => $offeredLine['org_supplier_id'],
                'org_supplier_product_id'    => $id,
                'supplier_product_id'        => $offeredLine['supplier_product_id'],
                'supplier_share_link_id'     => $link->id,
                'supplier_portal_contact_id' => $link->supplier_portal_contact_id,
                'state'                      => SupplierOrderProposalStateEnum::PENDING,
                'bucket'                     => $offeredLine['bucket'],
                'suggested_quantity_units'   => $offeredLine['quantity_units'],
                'quantity_units'             => (float) $line['quantity_units'],
                'unit_cost'                  => $offeredLine['unit_cost'],
                'supplier_note'              => $note,
            ]);
        })->values();
    }

    public function asController(string $token, Request $request): JsonResponse
    {
        $link = SupplierShareLink::findLiveByToken($token);
        abort_if($link->needsCode(), 403);

        $validated = Validator::make($request->all(), [
            'lines'                  => ['required', 'array', 'min:1', 'max:'.self::MAX_LINES],
            'lines.*.id'             => ['required', 'integer', 'distinct'],
            'lines.*.quantity_units' => ['required', 'numeric', 'gt:0'],
            'note'                   => ['nullable', 'string', 'max:2000'],
        ])->validate();

        $proposals = $this->handle($link, array_map(fn (array $line) => ['id' => (int) $line['id'], 'quantity_units' => (float) $line['quantity_units']], $validated['lines']), $validated['note'] ?? null);

        return response()->json([
            'proposed'  => $proposals->count(),
            'order_now' => GetSupplierPortalOrderNow::make()->handle($link->supplier),
        ]);
    }
}
