<?php

/*
 * Author Louis Perez
 * Created on 13-07-2026-09h-27m
 * GitHub: https://github.com/louis-perez
 * Copyright 2026
*/

namespace App\Actions\Ordering\Transaction;

use App\Actions\Dispatching\DeliveryNote\CalculateDeliveryNoteTotalAmounts;
use App\Actions\Dispatching\DeliveryNote\UpdateState\UndoPackingDeliveryNote;
use App\Actions\Dispatching\DeliveryNote\UpdateState\UndoSetAsPickedDeliveryNote;
use App\Actions\Dispatching\DeliveryNote\UpdateState\UnpackDeliveryNote;
use App\Actions\Dispatching\DeliveryNoteItem\CalculateDeliveryNoteItemTotalPicked;
use App\Actions\Ordering\Order\CalculateOrderTotalAmounts;
use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\Ordering\WithOrderingEditAuthorisation;
use App\Enums\Catalogue\Shop\ShopTypeEnum;
use App\Enums\Dispatching\DeliveryNote\DeliveryNoteStateEnum;
use App\Enums\Ordering\Platform\PlatformTypeEnum;
use App\Enums\Ordering\Order\OrderStateEnum;
use App\Events\BroadcastTransactionUpdated;
use App\Models\Catalogue\Product;
use App\Models\Dispatching\DeliveryNote;
use App\Models\Ordering\Order;
use App\Models\Ordering\Transaction;
use App\Models\SysAdmin\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\ActionRequest;

class UpdateTransactionProductQuantityOrdered extends OrgAction
{
    use WithOrderingEditAuthorisation;

    /**
     * @throws \Throwable
     */
    public function handle(Transaction $transaction, array $modelData, User $user): Transaction
    {
        if ($transaction->model_type != class_basename(Product::class)) {
            abort(422, __('Unable to modify this transaction (Not a valid product transaction)'));
        }

        $order = $transaction->order;

        if ($order->shop->type == ShopTypeEnum::EXTERNAL) {
            abort(422, __('Orders of external shops follow the external platform data and cannot be modified here'));
        }

        if ($order->platform && $order->platform->type != PlatformTypeEnum::MANUAL) {
            abort(422, __('Platform orders cannot be modified here'));
        }

        if (in_array($order->state, [OrderStateEnum::CANCELLED, OrderStateEnum::FINALISED, OrderStateEnum::DISPATCHED])) {
            abort(422, __('This order can no longer be modified'));
        }

        $product   = $transaction->model()->with('orgStocks')->first();
        $orgStocks = $product->orgStocks->keyBy('id');

        $transaction = DB::transaction(function () use ($transaction, $modelData, $user, $order, $orgStocks) {
            $deliveryNotes = $order
                ->deliveryNotes()
                ->whereNotIn('state', [
                    DeliveryNoteStateEnum::DISPATCHED,
                    DeliveryNoteStateEnum::CANCELLED,
                    DeliveryNoteStateEnum::FINALISED
                ])
                ->lockForUpdate()
                ->get();

            if ($deliveryNotes->isEmpty() && $order->deliveryNotes()->exists()) {
                abort(409, __('No editable delivery note available'));
            }

            $transaction = UpdateTransaction::make()->action($transaction, $modelData);
            $transaction->refresh();

            if (!$transaction->trashed()) {
                foreach ($deliveryNotes as $deliveryNote) {
                    $this->syncDeliveryNote($deliveryNote, $transaction, $orgStocks, $user);
                }
            }

            CalculateOrderTotalAmounts::run($order);
            foreach ($deliveryNotes as $deliveryNote) {
                CalculateDeliveryNoteTotalAmounts::run($deliveryNote);
            }

            $this->recordModification($order, $transaction, $modelData, $user);

            return $transaction;
        });

        BroadcastTransactionUpdated::dispatch($transaction, $order);

        return $transaction;
    }

    protected function syncDeliveryNote(DeliveryNote $deliveryNote, Transaction $transaction, $orgStocks, User $user): void
    {
        $goBackToPicking = false;
        $quantityLowered = false;

        $deliveryNoteItems = $transaction
            ->deliveryNoteItems()
            ->where('delivery_note_id', $deliveryNote->id)
            ->lockForUpdate()
            ->get();

        foreach ($deliveryNoteItems as $deliveryNoteItem) {
            $orgStock = $orgStocks->get($deliveryNoteItem->org_stock_id);
            if (!$orgStock) {
                continue;
            }

            $quantity            = $orgStock->pivot->quantity * ($transaction->quantity_ordered + $transaction->quantity_bonus);
            $oldRequiredQuantity = (float)$deliveryNoteItem->quantity_required;

            if (abs($quantity - $oldRequiredQuantity) < 0.000001) {
                continue;
            }

            $dataToBeUpdated = [
                'quantity_required' => $quantity,
                'is_dirty'          => true,
            ];

            if (!$deliveryNoteItem->original_quantity_required) {
                $dataToBeUpdated['original_quantity_required'] = $oldRequiredQuantity;
            }

            $deliveryNoteItem->update($dataToBeUpdated);

            if (abs($quantity - (float)$deliveryNoteItem->quantity_picked) > 0.000001) {
                $goBackToPicking = true;
            }
            if ($quantity < $oldRequiredQuantity) {
                $quantityLowered = true;
            }

            CalculateDeliveryNoteItemTotalPicked::make()->action($deliveryNoteItem);
        }

        if (!$goBackToPicking && !$quantityLowered) {
            return;
        }

        if ($deliveryNote->state == DeliveryNoteStateEnum::PACKED) {
            $deliveryNote = UnpackDeliveryNote::make()->action($deliveryNote, $user);
        }

        if ($goBackToPicking) {
            if ($deliveryNote->state == DeliveryNoteStateEnum::PACKING) {
                $deliveryNote = UndoPackingDeliveryNote::make()->action($deliveryNote, $user);
            }
            if ($deliveryNote->state == DeliveryNoteStateEnum::PICKED) {
                UndoSetAsPickedDeliveryNote::make()->action($deliveryNote, $user);
            }
        }
    }

    protected function recordModification(Order $order, Transaction $transaction, array $modelData, User $user): void
    {
        $modifications   = $order->post_submit_modification_data ?? [];
        $modifications[] = [
            'date_time'   => Carbon::now()->toDateTimeString(),
            'modified_by' => $user->username,
            'data'        => [
                'transaction_id' => $transaction->id,
                ...$modelData
            ]
        ];

        $order->update([
            'post_submit_modification_data' => $modifications
        ]);
    }

    public function rules(): array
    {
        return [
            'units_ordered'    => ['sometimes', 'numeric', 'integer', 'min:0'],
            'quantity_ordered' => ['sometimes', 'numeric', 'min:0'],
        ];
    }

    /**
     * @throws \Throwable
     */
    public function asController(Transaction $transaction, ActionRequest $request): Transaction
    {
        $this->initialisationFromShop($transaction->shop, $request);

        return $this->handle($transaction, $this->validatedData, $request->user());
    }

    /**
     * @throws \Throwable
     */
    public function action(Transaction $transaction, array $modelData, User $user): Transaction
    {
        $this->asAction = true;
        $this->initialisationFromShop($transaction->shop, $modelData);

        return $this->handle($transaction, $this->validatedData, $user);
    }

    public function jsonResponse(Transaction $transaction): Transaction
    {
        return $transaction;
    }
}
