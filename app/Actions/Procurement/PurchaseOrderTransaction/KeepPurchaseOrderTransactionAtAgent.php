<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 25 Nov 2026 12:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Procurement\PurchaseOrderTransaction;

use App\Actions\GoodsIn\StockDelivery\StoreStockDeliveryFromPurchaseOrder;
use App\Actions\GoodsIn\StockDelivery\UpdatePurchaseOrdersDeliveryStateFromStockDelivery;
use App\Actions\OrgAction;
use App\Actions\Procurement\PurchaseOrder\Hydrators\PurchaseOrderHydrateState;
use App\Enums\GoodsIn\StockDelivery\StockDeliveryStateEnum;
use App\Enums\Procurement\PurchaseOrderTransaction\PurchaseOrderTransactionStateEnum;
use App\Models\GoodsIn\StockDelivery;
use App\Models\Procurement\PurchaseOrder;
use App\Models\Procurement\PurchaseOrderTransaction;
use App\Models\SysAdmin\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Lorisleiva\Actions\ActionRequest;

/**
 * INI-525. The buyer agrees that the rest of an agent order's line stays at the agent: those units count as accounted
 * for, so the order reaches Shipped and settles with the containers that carry the rest, and they can still go in a
 * later container. Only for a line with some units in a container already.
 */
class KeepPurchaseOrderTransactionAtAgent extends OrgAction
{
    public const int MIN_NOTE_LENGTH = 4;

    private PurchaseOrderTransaction $purchaseOrderTransaction;

    public static function onContainers(PurchaseOrderTransaction $purchaseOrderTransaction): float
    {
        return (float) PurchaseOrderTransaction::whereKey($purchaseOrderTransaction->id)
            ->selectSub(StoreStockDeliveryFromPurchaseOrder::quantityOnDelivery(), 'on_containers')
            ->value('on_containers');
    }

    /**
     * The units that may still be kept: in no container and not kept yet. Zero when the line can not be kept.
     */
    public static function keepableQuantity(PurchaseOrderTransaction $purchaseOrderTransaction, ?float $onContainers = null): float
    {
        $purchaseOrder = $purchaseOrderTransaction->purchaseOrder;

        if (!$purchaseOrder?->isAgentOrder() || !$purchaseOrder->state->isAgreed() || $purchaseOrderTransaction->state !== PurchaseOrderTransactionStateEnum::CONFIRMED) {
            return 0;
        }

        $onContainers ??= self::onContainers($purchaseOrderTransaction);
        if ($onContainers <= StoreStockDeliveryFromPurchaseOrder::QUANTITY_TOLERANCE) {
            return 0;
        }

        $keepable = round((float) $purchaseOrderTransaction->quantity_ordered - $onContainers - (float) $purchaseOrderTransaction->quantity_kept_at_agent, 3);

        return $keepable > StoreStockDeliveryFromPurchaseOrder::QUANTITY_TOLERANCE ? $keepable : 0;
    }

    public static function userCanKeep(PurchaseOrder $purchaseOrder, ?User $user): bool
    {
        $agentOrganisationId = $purchaseOrder->isAgentOrder() ? $purchaseOrder->agent?->organisation_id : null;

        return $agentOrganisationId !== null && (bool) $user?->authTo("procurement.$agentOrganisationId.edit");
    }

    public function handle(PurchaseOrderTransaction $purchaseOrderTransaction, float $quantity, string $note, ?int $userId): PurchaseOrderTransaction
    {
        DB::transaction(function () use ($purchaseOrderTransaction, $quantity, $note, $userId) {
            PurchaseOrder::whereKey($purchaseOrderTransaction->purchase_order_id)->lockForUpdate()->first();
            $purchaseOrderTransaction = PurchaseOrderTransaction::whereKey($purchaseOrderTransaction->id)->lockForUpdate()->firstOrFail();

            $keepable = self::keepableQuantity($purchaseOrderTransaction);
            if ($keepable <= 0 || $quantity > $keepable + StoreStockDeliveryFromPurchaseOrder::QUANTITY_TOLERANCE) {
                throw ValidationException::withMessages(['quantity' => $keepable <= 0
                    ? __('Nothing of this item can be kept at the agent')
                    : __('Keep at most :units units', ['units' => $keepable])]);
            }

            $data                    = $purchaseOrderTransaction->data ?? [];
            $data['kept_at_agent'][] = [
                'quantity' => round($quantity, 3),
                'note'     => $note,
                'user_id'  => $userId,
                'at'       => now()->toIso8601String(),
            ];

            $purchaseOrderTransaction->update([
                'quantity_kept_at_agent' => round((float) $purchaseOrderTransaction->quantity_kept_at_agent + $quantity, 3),
                'data'                   => $data,
            ]);
        });

        self::resyncPurchaseOrder($purchaseOrderTransaction->purchaseOrder);

        return $purchaseOrderTransaction->refresh();
    }

    /**
     * A container already in may now settle the order, and the stages move with what is still awaited.
     */
    public static function resyncPurchaseOrder(PurchaseOrder $purchaseOrder): void
    {
        $purchaseOrder->stockDeliveries()
            ->whereIn('stock_deliveries.state', [StockDeliveryStateEnum::RECEIVED, StockDeliveryStateEnum::CHECKED, StockDeliveryStateEnum::PLACED])
            ->get()
            ->each(fn (StockDelivery $stockDelivery) => UpdatePurchaseOrdersDeliveryStateFromStockDelivery::run($stockDelivery));

        PurchaseOrderHydrateState::run($purchaseOrder->refresh());
    }

    public function authorize(ActionRequest $request): bool
    {
        if ($this->asAction) {
            return true;
        }

        return self::userCanKeep($this->purchaseOrderTransaction->purchaseOrder, $request->user());
    }

    public function prepareForValidation(): void
    {
        if (is_string($this->get('note'))) {
            $this->set('note', trim($this->get('note')));
        }
    }

    public function rules(): array
    {
        return [
            'quantity' => ['required', 'numeric', 'min:0.001'],
            'note'     => ['required', 'string', 'min:'.self::MIN_NOTE_LENGTH, 'max:1000'],
        ];
    }

    public function getValidationMessages(): array
    {
        return [
            'note.required' => __('Write why the rest stays at the agent'),
            'note.min'      => __('Write at least :min characters', ['min' => self::MIN_NOTE_LENGTH]),
        ];
    }

    public function action(PurchaseOrderTransaction $purchaseOrderTransaction, array $modelData, ?int $userId = null): PurchaseOrderTransaction
    {
        $this->asAction                 = true;
        $this->purchaseOrderTransaction = $purchaseOrderTransaction;
        $this->initialisation($purchaseOrderTransaction->organisation, $modelData);

        return $this->handle($purchaseOrderTransaction, (float) $this->validatedData['quantity'], $this->validatedData['note'], $userId);
    }

    public function asController(PurchaseOrder $purchaseOrder, PurchaseOrderTransaction $purchaseOrderTransaction, ActionRequest $request): RedirectResponse
    {
        abort_unless($purchaseOrderTransaction->purchase_order_id === $purchaseOrder->id, 404);
        $this->purchaseOrderTransaction = $purchaseOrderTransaction;
        $this->initialisation($purchaseOrderTransaction->organisation, $request);

        $this->handle($purchaseOrderTransaction, (float) $this->validatedData['quantity'], $this->validatedData['note'], $request->user()->id);

        return back();
    }
}
