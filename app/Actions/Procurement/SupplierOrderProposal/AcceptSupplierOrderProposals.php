<?php

/*
    * Author: Vika Aqordi
    * Created on: 2026-11-27 10:00
    * Github: https://github.com/aqordeon
    * Copyright: 2026
*/

namespace App\Actions\Procurement\SupplierOrderProposal;

use App\Actions\OrgAction;
use App\Actions\Procurement\PurchaseOrder\CalculatePurchaseOrderTotalAmounts;
use App\Actions\Procurement\PurchaseOrder\Hydrators\PurchaseOrderHydrateTransactions;
use App\Actions\Procurement\PurchaseOrder\StorePurchaseOrder;
use App\Actions\Procurement\PurchaseOrder\UpdatePurchaseOrderTransactionQuantity;
use App\Actions\Procurement\PurchaseOrderTransaction\StorePurchaseOrderTransaction;
use App\Actions\Traits\Authorisations\WithProcurementEditAuthorisation;
use App\Enums\Procurement\PurchaseOrder\PurchaseOrderStateEnum;
use App\Enums\Procurement\PurchaseOrderTransaction\PurchaseOrderTransactionStateEnum;
use App\Enums\Procurement\SupplierOrderProposal\SupplierOrderProposalStateEnum;
use App\Models\Procurement\OrgSupplier;
use App\Models\Procurement\PurchaseOrder;
use App\Models\Procurement\SupplierOrderProposal;
use App\Models\SysAdmin\Organisation;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Lorisleiva\Actions\ActionRequest;

/**
 * The buyer accepts lines a supplier proposed from its portal: each goes on the supplier's draft purchase order
 * (the newest in process, or a new one), in the quantity the buyer settles, kept apart from the supplier's own so
 * the supplier sees both. When the draft already has the product,
 * the line keeps the larger of the two quantities instead of adding them, because both answer the same shortage.
 */
class AcceptSupplierOrderProposals extends OrgAction
{
    use WithProcurementEditAuthorisation;

    /**
     * @param  list<array{id: int, quantity_units?: float|null}>  $lines
     *
     * @return array{accepted: int, purchase_orders: list<PurchaseOrder>, skipped: list<array{code: string|null, reason: string}>}
     *
     * @throws ValidationException
     */
    public function handle(Organisation $organisation, array $lines, int $userId): array
    {
        $quantities = collect($lines)->mapWithKeys(fn (array $line) => [(int) $line['id'] => $line['quantity_units'] ?? null]);

        return DB::transaction(function () use ($organisation, $quantities, $userId) {
            $proposals = SupplierOrderProposal::where('organisation_id', $organisation->id)
                ->whereIn('id', $quantities->keys())
                ->where('state', SupplierOrderProposalStateEnum::PENDING)
                ->with(['orgSupplier', 'orgSupplierProduct.supplierProduct', 'supplierProduct'])
                ->lockForUpdate()
                ->get();

            if ($proposals->isEmpty()) {
                throw ValidationException::withMessages(['proposals' => __('These lines were already decided')]);
            }

            $purchaseOrders = [];
            $skipped        = [];
            $accepted       = 0;

            foreach ($proposals->groupBy('org_supplier_id') as $proposalsOfOrgSupplier) {
                /** @var OrgSupplier $orgSupplier */
                $orgSupplier   = $proposalsOfOrgSupplier->first()->orgSupplier;
                $purchaseOrder = $this->draftPurchaseOrder($orgSupplier);
                $added         = $this->addLines($purchaseOrder, $proposalsOfOrgSupplier, $quantities, $userId, $skipped);

                if ($added > 0) {
                    CalculatePurchaseOrderTotalAmounts::run($purchaseOrder);
                    PurchaseOrderHydrateTransactions::run($purchaseOrder);
                    $purchaseOrders[] = $purchaseOrder;
                    $accepted += $added;
                }
            }

            return ['accepted' => $accepted, 'purchase_orders' => $purchaseOrders, 'skipped' => $skipped];
        });
    }

    private function draftPurchaseOrder(OrgSupplier $orgSupplier): PurchaseOrder
    {
        OrgSupplier::whereKey($orgSupplier->id)->lockForUpdate()->first();

        return PurchaseOrder::where('parent_type', 'OrgSupplier')
            ->where('parent_id', $orgSupplier->id)
            ->where('state', PurchaseOrderStateEnum::IN_PROCESS)
            ->latest('id')
            ->first() ?? StorePurchaseOrder::make()->action($orgSupplier, []);
    }

    /**
     * @param  Collection<int, SupplierOrderProposal>  $proposals
     * @param  Collection<int, float|null>  $quantities
     * @param  list<array{code: string|null, reason: string}>  $skipped
     */
    private function addLines(PurchaseOrder $purchaseOrder, Collection $proposals, Collection $quantities, int $userId, array &$skipped): int
    {
        $storeTransaction          = StorePurchaseOrderTransaction::make();
        $storeTransaction->batched = true;
        $added                     = 0;

        foreach ($proposals as $proposal) {
            $quantity = (float) ($quantities->get($proposal->id) ?? $proposal->quantity_units);
            if ($quantity <= 0) {
                $skipped[] = ['code' => $proposal->supplierProduct?->code, 'reason' => __('The quantity must be more than zero')];
                continue;
            }

            $existingLine = $purchaseOrder->purchaseOrderTransactions()
                ->where('supplier_product_id', $proposal->supplier_product_id)
                ->whereNotIn('state', [PurchaseOrderTransactionStateEnum::CANCELLED, PurchaseOrderTransactionStateEnum::NOT_RECEIVED])
                ->oldest('id')
                ->first();

            try {
                $transaction = $existingLine
                    ? ((float) $existingLine->quantity_ordered >= $quantity ? $existingLine : UpdatePurchaseOrderTransactionQuantity::make()->action($existingLine, ['quantity_ordered' => $quantity]))
                    : $storeTransaction->addOrgSupplierProduct($purchaseOrder, $proposal->orgSupplierProduct, ['quantity_ordered' => $quantity]);
            } catch (ValidationException $exception) {
                $skipped[] = ['code' => $proposal->supplierProduct?->code, 'reason' => collect($exception->errors())->flatten()->first() ?? __('Could not be added')];
                continue;
            }

            $proposal->update([
                'state'                         => SupplierOrderProposalStateEnum::ACCEPTED,
                'accepted_quantity_units'       => $quantity,
                'decided_by_user_id'            => $userId,
                'decided_at'                    => now(),
                'purchase_order_id'             => $purchaseOrder->id,
                'purchase_order_transaction_id' => $transaction->id,
            ]);
            $added++;
        }

        return $added;
    }

    public function rules(): array
    {
        return [
            'lines'                  => ['required', 'array', 'min:1', 'max:500'],
            'lines.*.id'             => ['required', 'integer', 'distinct'],
            'lines.*.quantity_units' => ['nullable', 'numeric', 'gt:0'],
        ];
    }

    public function asController(Organisation $organisation, ActionRequest $request): array
    {
        $this->initialisation($organisation, $request);

        return $this->handle($organisation, $this->validatedData['lines'], $request->user()->id);
    }

    /**
     * @return array{accepted: int, purchase_orders: list<array{reference: string, url: string}>, skipped: list<array{code: string|null, reason: string}>}
     */
    public function jsonResponse(array $result): array
    {
        return [
            'accepted'        => $result['accepted'],
            'purchase_orders' => collect($result['purchase_orders'])->map(fn (PurchaseOrder $purchaseOrder) => [
                'reference' => $purchaseOrder->reference,
                'url'       => route('grp.org.procurement.purchase_orders.show', [$purchaseOrder->organisation->slug, $purchaseOrder->slug]),
            ])->unique('reference')->values()->all(),
            'skipped'         => $result['skipped'],
        ];
    }

    public function htmlResponse(): RedirectResponse
    {
        return back();
    }
}
