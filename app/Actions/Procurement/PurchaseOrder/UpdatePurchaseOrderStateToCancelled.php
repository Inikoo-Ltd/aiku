<?php

/*
 * author Arya Permana - Kirin
 * created on 12-11-2024-10h-35m
 * github: https://github.com/KirinZero0
 * copyright 2024
 */

namespace App\Actions\Procurement\PurchaseOrder;

use App\Actions\Traits\Authorisations\WithPurchaseOrderAgentEditAuthorisation;
use App\Actions\OrgAction;
use App\Actions\Procurement\PurchaseOrder\Hydrators\PurchaseOrderHydrateTransactions;
use App\Actions\Procurement\PurchaseOrder\Traits\HasPurchaseOrderHydrators;
use App\Actions\Traits\WithActionUpdate;
use App\Enums\GoodsIn\StockDelivery\StockDeliveryStateEnum;
use App\Enums\Procurement\PurchaseOrder\PurchaseOrderStateEnum;
use App\Enums\Procurement\PurchaseOrderTransaction\PurchaseOrderTransactionStateEnum;
use App\Http\Resources\Procurement\PurchaseOrderResource;
use App\Models\Procurement\PurchaseOrder;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;

class UpdatePurchaseOrderStateToCancelled extends OrgAction
{
    use WithPurchaseOrderAgentEditAuthorisation;
    use AsAction;
    use HasPurchaseOrderHydrators;
    use WithActionUpdate;

    public const string SOURCE_USER = 'user';
    public const string SOURCE_MOVED = 'moved';
    public const string SOURCE_EMPTIED = 'emptied';

    /**
     * Keeps in data.cancellation what cancelling wipes (state, the live lines, their states and amounts) so
     * UpdatePurchaseOrderStateFromCancelled can bring the order back.
     */
    public function handle(PurchaseOrder $purchaseOrder, ?string $reason = null, string $source = self::SOURCE_USER): PurchaseOrder
    {
        return DB::transaction(function () use ($purchaseOrder, $reason, $source) {
            $purchaseOrder = PurchaseOrder::whereKey($purchaseOrder->id)->lockForUpdate()->firstOrFail();

            if (!in_array($purchaseOrder->state, PurchaseOrderStateEnum::open())) {
                abort(422, __('Only submitted or confirmed purchase orders can be cancelled'));
            }

            if ($purchaseOrder->state->isAgreed() && $purchaseOrder->stockDeliveries()->where('stock_deliveries.state', '!=', StockDeliveryStateEnum::CANCELLED)->exists()) {
                abort(422, __('A confirmed purchase order with stock deliveries cannot be cancelled'));
            }

            $cancellation = [
                'state'        => $purchaseOrder->state->value,
                'transactions' => $purchaseOrder->purchaseOrderTransactions()
                    ->where('state', '!=', PurchaseOrderTransactionStateEnum::CANCELLED)
                    ->get(['id', 'state', 'net_amount', 'grp_net_amount', 'org_net_amount'])
                    ->map(fn ($transaction) => [
                        'id'             => $transaction->id,
                        'state'          => $transaction->state->value,
                        'net_amount'     => (float) $transaction->net_amount,
                        'grp_net_amount' => $transaction->grp_net_amount === null ? null : (float) $transaction->grp_net_amount,
                        'org_net_amount' => $transaction->org_net_amount === null ? null : (float) $transaction->org_net_amount,
                    ])
                    ->values()
                    ->all(),
                'source'       => $source,
                'by'           => auth()->id(),
                'at'           => now()->toIso8601String(),
                'reason'       => $reason ?: null,
            ];

            $purchaseOrder->purchaseOrderTransactions()->update([
                'state'          => PurchaseOrderTransactionStateEnum::CANCELLED,
                'net_amount'     => 0,
                'grp_net_amount' => 0,
                'org_net_amount' => 0,
            ]);

            $purchaseOrder = $this->update($purchaseOrder, [
                'state'        => PurchaseOrderStateEnum::CANCELLED,
                'cancelled_at' => now(),
                'data'         => array_merge($purchaseOrder->data ?? [], ['cancellation' => $cancellation]),
            ]);

            if ($reason) {
                MarkPurchaseOrderStage::auditOverride($purchaseOrder, 'state', $cancellation['state'], PurchaseOrderStateEnum::CANCELLED->value, $reason);
            }

            CalculatePurchaseOrderTotalAmounts::run($purchaseOrder);
            PurchaseOrderHydrateTransactions::dispatch($purchaseOrder)->afterCommit();

            $this->purchaseOrderHydrate($purchaseOrder);

            return $purchaseOrder;
        });
    }

    public function rules(): array
    {
        if ($this->asAction) {
            return [];
        }

        return [
            'counterparty_informed' => ['required', 'string', 'in:yes,Yes,YES'],
            'reason'                => ['sometimes', 'nullable', 'string', 'max:500'],
        ];
    }

    public function getValidationMessages(): array
    {
        return [
            'counterparty_informed.*' => __('Type yes to confirm you have informed the supplier, agent or partner that this order is cancelled.'),
        ];
    }

    public function asController(PurchaseOrder $purchaseOrder, ActionRequest $request): PurchaseOrder
    {
        $this->initialisation($purchaseOrder->organisation, $request);

        return $this->handle($purchaseOrder, trim((string) ($this->validatedData['reason'] ?? '')));
    }

    public function action(PurchaseOrder $purchaseOrder): PurchaseOrder
    {
        $this->asAction      = true;
        $this->purchaseOrder = $purchaseOrder;
        $this->initialisation($purchaseOrder->organisation, []);

        return $this->handle($purchaseOrder);
    }

    public function jsonResponse(PurchaseOrder $purchaseOrder): PurchaseOrderResource
    {
        return new PurchaseOrderResource($purchaseOrder);
    }
}
