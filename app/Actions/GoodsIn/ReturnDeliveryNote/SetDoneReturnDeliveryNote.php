<?php

/*
 * author Louis Perez
 * created on 18-05-2026-16h-18m
 * github: https://github.com/louis-perez
 * copyright 2026
*/

namespace App\Actions\GoodsIn\ReturnDeliveryNote;

use App\Actions\Accounting\Invoice\StoreRefund;
use App\Actions\Accounting\Invoice\UI\FinaliseRefund;
use App\Actions\Accounting\InvoiceTransaction\StoreRefundInvoiceTransaction;
use App\Actions\GoodsIn\ReturnDeliveryNote\Traits\WithHydrateReturnDeliveryNotes;
use App\Actions\GoodsIn\ReturnDeliveryNoteItem\UpdateReturnDeliveryNoteItem;
use App\Actions\OrgAction;
use App\Actions\Traits\WithActionUpdate;
use App\Enums\Accounting\Invoice\InvoiceTypeEnum;
use App\Enums\GoodsIn\ReturnDeliveryNote\ReturnDeliveryNoteStateEnum;
use App\Enums\GoodsIn\ReturnDeliveryNoteItem\ReturnDeliveryNoteItemStateEnum;
use App\Models\Catalogue\Product;
use App\Models\GoodsIn\ReturnDeliveryNote;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Lorisleiva\Actions\ActionRequest;

class SetDoneReturnDeliveryNote extends OrgAction
{
    use WithActionUpdate;
    use WithHydrateReturnDeliveryNotes;

    public function handle(ReturnDeliveryNote $returnDeliveryNote, array $modelData): ReturnDeliveryNote
    {
        $user = request()->user();
        $oldState = $returnDeliveryNote->state;
        $originalInvoice = $returnDeliveryNote->order->invoices()->where('type', InvoiceTypeEnum::INVOICE)->first();

        if ($oldState !== ReturnDeliveryNoteStateEnum::RETURNED) {
            throw ValidationException::withMessages([
                'message' => __('Return cannot be finished.').' ['.__('Invalid state').': '.$oldState->value.']',
            ]);
        }

        if (!$originalInvoice) {
            throw ValidationException::withMessages([
                'message' => __('Return cannot be finished. Missing invoice detected'),
            ]);
        }

        data_set($modelData, 'state', ReturnDeliveryNoteStateEnum::DONE);
        data_set($modelData, 'handler_user_id', $user->id);

        $returnDeliveryNote = DB::transaction(function () use ($returnDeliveryNote, $modelData, $originalInvoice) {
            $refundData = Arr::pull($modelData, 'refundedData', []);
            
            $refund = StoreRefund::make()->action($originalInvoice, []);
            $refund->refresh();

            $lastRefundItem = null;
            foreach($originalInvoice->invoiceTransactions as $invoiceTransaction) {
                if (!$invoiceTransaction->transaction_id) continue;

                $refundedItem = data_get($refundData, $invoiceTransaction->transaction_id, null);

                if ($refundedItem) {
                    
                    StoreRefundInvoiceTransaction::make()->action($refund, $invoiceTransaction, [
                        'net_amount'    => data_get($refundedItem, 'refund_amount', 0),
                    ]);
                }
            }

            FinaliseRefund::make()->action($refund, []);
            $refund->refresh();

            data_set($modelData, 'refund_id', $refund->id);

            $returnDeliveryNote = UpdateReturnDeliveryNote::make()->action($returnDeliveryNote, $modelData);
            $refundedItem = $refund->invoiceTransactions;

            foreach ($returnDeliveryNote->returnDeliveryNoteItem as $item) {

                $refundedItemData = $refundedItem->where('transaction_id', $item->original_transaction_id)->first();
                $updatedData = [
                    'state'                 => ReturnDeliveryNoteItemStateEnum::PROCESSED,
                    'refunded_amount'       => $refundedItemData?->refund_amount ?? 0,
                ];

                if ($refundedItemData) {
                    data_set($updatedData, 'refund_transaction_id', $refundedItemData->id);
                }
                
                UpdateReturnDeliveryNoteItem::make()->action($item, $updatedData);

            }

            $returnDeliveryNote->refresh();

            return $returnDeliveryNote;
        });

        $this->hydrateReturnDeliveryNotes($returnDeliveryNote);

        return $returnDeliveryNote;
    }

    public function rules(): array
    {
        return [
            'refundedData'                              => ['sometimes', 'array'],
            'refundedData.*.quantity'                   => ['sometimes', 'numeric'],
            'refundedData.*.refund_amount'              => ['sometimes', 'numeric'],
        ];
    }

    public function asController(ReturnDeliveryNote $returnDeliveryNote, ActionRequest $request): ReturnDeliveryNote
    {
        $this->initialisationFromWarehouse($returnDeliveryNote->warehouse, $request);

        return $this->handle($returnDeliveryNote, $this->validatedData);
    }
}
