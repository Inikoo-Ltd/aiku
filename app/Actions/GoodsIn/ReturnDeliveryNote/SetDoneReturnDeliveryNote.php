<?php

/*
 * author Louis Perez
 * created on 18-05-2026-16h-18m
 * GitHub: https://github.com/louis-perez
 * copyright 2026
*/

namespace App\Actions\GoodsIn\ReturnDeliveryNote;

use App\Actions\Accounting\Invoice\StoreRefund;
use App\Actions\Accounting\Invoice\UI\FinaliseRefund;
use App\Actions\Accounting\InvoiceTransaction\StoreRefundInvoiceTransaction;
use App\Actions\Dispatching\DeliveryNote\StoreReplacementDeliveryNote;
use App\Actions\GoodsIn\ReturnDeliveryNote\Traits\WithHydrateReturnDeliveryNotes;
use App\Actions\GoodsIn\ReturnDeliveryNote\Traits\WithReturnDeliveryNoteTransition;
use App\Actions\GoodsIn\ReturnDeliveryNoteItem\UpdateReturnDeliveryNoteItem;
use App\Actions\OrgAction;
use App\Actions\Traits\WithActionUpdate;
use App\Enums\Accounting\Invoice\InvoiceTypeEnum;
use App\Enums\GoodsIn\ReturnDeliveryNote\ReturnDeliveryNoteStateEnum;
use App\Enums\GoodsIn\ReturnDeliveryNoteItem\ReturnDeliveryNoteItemStateEnum;
use App\Models\Accounting\Invoice;
use App\Models\Dispatching\DeliveryNote;
use App\Models\GoodsIn\ReturnDeliveryNote;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Lorisleiva\Actions\ActionRequest;

class SetDoneReturnDeliveryNote extends OrgAction
{
    use WithActionUpdate;
    use WithHydrateReturnDeliveryNotes;
    use WithReturnDeliveryNoteTransition;

    /**
     * @throws \Throwable
     * @throws \Illuminate\Validation\ValidationException
     */
    public function handle(ReturnDeliveryNote $returnDeliveryNote, array $modelData): ReturnDeliveryNote
    {
        $user = request()->user();
        $originalInvoice = $returnDeliveryNote->order->invoices()->where('type', InvoiceTypeEnum::INVOICE)->first();

        $this->validateReturnDeliveryNoteState($returnDeliveryNote, ReturnDeliveryNoteStateEnum::RETURNED, 'Return cannot be finished.');

        if (!$originalInvoice) {
            throw ValidationException::withMessages([
                'message' => __('Return cannot be finished. Missing invoice detected'),
            ]);
        }

        data_set($modelData, 'state', ReturnDeliveryNoteStateEnum::DONE);
        data_set($modelData, 'handler_user_id', $user->id);

        $returnDeliveryNote = DB::transaction(function () use ($returnDeliveryNote, $modelData, $originalInvoice) {
            $createRefund = Arr::pull($modelData, 'createRefund', false);
            $createReplacement = Arr::pull($modelData, 'createReplacement', false);

            if ($createRefund) {
                $refundData = array_filter(Arr::get($modelData, 'refundedData', []), fn ($item) => data_get($item, 'refund_amount') > 0);

                $refund = $this->processRefund($returnDeliveryNote, $originalInvoice, $refundData);
                data_set($modelData, 'refund_id', $refund->id);
            }
            if ($createReplacement) {
                $replacementData = array_filter(Arr::get($modelData, 'refundedData', []), fn ($item) => data_get($item, 'replaced_quantity') > 0);

                $replacement = $this->processReplacement($returnDeliveryNote, $replacementData);
                data_set($modelData, 'replacement_id', $replacement->id);

            }

            unset($modelData['refundedData']);

            $returnDeliveryNote = UpdateReturnDeliveryNote::make()->action($returnDeliveryNote, $modelData);

            $returnDeliveryNote->refresh();

            return $returnDeliveryNote;
        });

        $this->hydrateReturnDeliveryNotes($returnDeliveryNote);

        return $returnDeliveryNote;
    }

    /**
     * @throws \Throwable
     */
    public function processRefund(ReturnDeliveryNote $returnDeliveryNote, Invoice $originalInvoice, array $refundData): Invoice
    {
        $refund = StoreRefund::make()->action($originalInvoice, []);
        $refund->refresh();


        foreach ($originalInvoice->invoiceTransactions as $invoiceTransaction) {
            if (!$invoiceTransaction->transaction_id) {
                continue;
            }

            $refundedItem = data_get($refundData, $invoiceTransaction->transaction_id);

            if ($refundedItem) {
                StoreRefundInvoiceTransaction::make()->action($refund, $invoiceTransaction, [
                    'net_amount'    => data_get($refundedItem, 'refund_amount', 0),
                ]);
            }
        }

        FinaliseRefund::make()->action($refund, []);
        $refund->refresh();

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

        return $refund;
    }

    /**
     * @throws \Throwable
     */
    public function processReplacement(ReturnDeliveryNote $returnDeliveryNote, array $replacementData): DeliveryNote
    {
        $replacementData = collect($replacementData)->map(function ($item) {
            return [
                'id'        => data_get($item, 'delivery_note_items_id'),
                'quantity'  => data_get($item, 'replaced_quantity'),
            ];
        });

        return StoreReplacementDeliveryNote::make()->action($returnDeliveryNote->order, [
            'delivery_note_items' => $replacementData->toArray(),
            'warehouse_id'        => $returnDeliveryNote->warehouse_id,
            'reference'           => $returnDeliveryNote->order->reference,
        ]);
    }

    public function rules(): array
    {
        return [
            'refundedData'                              => ['sometimes', 'array'],
            'refundedData.*.quantity'                   => ['sometimes', 'numeric'],
            'refundedData.*.delivery_note_items_id'     => ['sometimes', 'numeric'],
            'refundedData.*.refund_amount'              => ['sometimes', 'numeric'],
            'refundedData.*.replaced_quantity'          => ['sometimes', 'numeric'],
            'createRefund'                              => ['required', 'boolean'],
            'createReplacement'                         => ['required', 'boolean'],
        ];
    }

    /**
     * @throws \Throwable
     * @throws \Illuminate\Validation\ValidationException
     */
    public function asController(ReturnDeliveryNote $returnDeliveryNote, ActionRequest $request): ReturnDeliveryNote
    {
        $this->initialisationFromWarehouse($returnDeliveryNote->warehouse, $request);

        return $this->handle($returnDeliveryNote, $this->validatedData ?? []);
    }

}
