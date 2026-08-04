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
use App\Actions\Dispatching\Picking\UpdatePicking;
use App\Actions\Ordering\Order\CalculateOrderTotalAmounts;
use App\Actions\OrgAction;
use App\Enums\Dispatching\DeliveryNote\DeliveryNoteStateEnum;
use App\Enums\Dispatching\Picking\PickingTypeEnum;
use App\Events\BroadcastTransactionUpdated;
use App\Models\Catalogue\Product;
use App\Models\Ordering\Transaction;
use Lorisleiva\Actions\ActionRequest;

class UpdateTransactionProductQuantityOrdered extends OrgAction
{
    public function handle(Transaction $transaction, array $modelData)
    {
        if ($transaction->model_type != class_basename(Product::class)) {
            abort(422, __('Unable to modify this transaction (Not a valid product transaction)'));
        }

        $product = $transaction->model()->with('orgStocks')->first();
        $orgStocks = $product->orgStocks->keyBy('id');

        $deliveryNote = $transaction
            ->order
            ->deliveryNotes()
            ->whereNotIn('state', [
                DeliveryNoteStateEnum::DISPATCHED,
                DeliveryNoteStateEnum::CANCELLED,
                DeliveryNoteStateEnum::FINALISED
            ])
            ->first();

        if (!$deliveryNote) {
            abort(409, __('No editable delivery note available'));
        }

        $order = $transaction->order;

        $user = request()?->user();
        $goBackToPicking = false;

        $transaction = UpdateTransaction::make()->action($transaction, $modelData);
        $transaction->refresh();

        // Ignore 0 quantity anyway, those Delivery Note Items (Including Picking & Packing) will be deleted on UpdateTransaction
        foreach ($transaction->deliveryNoteItems as $deliveryNoteItem) {
            if ($deliveryNoteItem->delivery_note_id !== $deliveryNote->id) continue;
            
            $orgStock = $orgStocks->get($deliveryNoteItem->org_stock_id);
            $quantity = $orgStock->pivot->quantity * ($transaction->quantity_ordered + $transaction->quantity_bonus);
            $oldRequiredQuantity = $deliveryNoteItem->quantity_required;

            $dataToBeUpdated = [
                'quantity_required' => $quantity,
                'is_dirty'          => true,
            ];

            if (!$deliveryNoteItem->original_quantity_required) {
                $dataToBeUpdated['original_quantity_required'] = $oldRequiredQuantity;
            }

            $deliveryNoteItem->update($dataToBeUpdated);

            if ($quantity === $oldRequiredQuantity) {
                continue;
            }

            // Set go back to picking if have item to pick again
            if ($quantity > $deliveryNoteItem->quantity_picked) {
                $goBackToPicking = true;
            }

            CalculateDeliveryNoteItemTotalPicked::make()->action($deliveryNoteItem);
        }

        if ($deliveryNote->state == DeliveryNoteStateEnum::PACKED) {
            $deliveryNote = UnpackDeliveryNote::make()->action($deliveryNote, $user);
        }

        // Massive Rollbacks
        if ($goBackToPicking) {
            if ($deliveryNote->state == DeliveryNoteStateEnum::PACKING) {
                $deliveryNote = UndoPackingDeliveryNote::make()->action($deliveryNote, $user);
            }
            if ($deliveryNote->state == DeliveryNoteStateEnum::PICKED) {
                $deliveryNote = UndoSetAsPickedDeliveryNote::make()->action($deliveryNote, $user);
            }
        }

        CalculateOrderTotalAmounts::run($order);
        CalculateDeliveryNoteTotalAmounts::run($deliveryNote);

        BroadcastTransactionUpdated::dispatch($transaction, $order);

        return $transaction;
    }

    public function rules(): array
    {
        return [
            'units_ordered'       => ['sometimes', 'numeric', 'integer', 'min:0'],
            'quantity_ordered'    => ['sometimes', 'numeric', 'min:0'],
        ];
    }

    public function asController(Transaction $transaction, ActionRequest $request)
    {
        $this->initialisationFromShop($transaction->shop, $request);

        $this->handle($transaction, $this->validatedData);
    }

    public function jsonResponse(Transaction $transaction)
    {
        return $transaction;
    }
}
