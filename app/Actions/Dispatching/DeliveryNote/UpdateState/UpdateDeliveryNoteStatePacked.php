<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 25 Feb 2026 11:40:43 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Dispatching\DeliveryNote\UpdateState;

use App\Actions\Catalogue\Shop\Hydrators\HasDeliveryNoteHydrators;
use App\Actions\Dispatching\DeliveryNote\DeliveryNoteBoxPackingList;
use App\Actions\Dispatching\DeliveryNote\Hydrators\DeliveryNoteHydrateTrolleys;
use App\Actions\Dispatching\DeliveryNoteItem\UpdateDeliveryNoteItemPacking;
use App\Actions\Dispatching\Packing\StorePacking;
use App\Actions\Dispatching\PickingSession\AutoFinishPackingPickingSession;
use App\Actions\Dispatching\Shipment\StoreShipmentFromFaire;
use App\Actions\Dropshipping\Tiktok\Order\ProcessTiktokOrderShipment;
use App\Actions\Ordering\Order\GenerateInvoiceFromOrder;
use App\Actions\Ordering\Order\UpdateState\UpdateOrderStateBackToFinalised;
use App\Actions\Ordering\Order\UpdateState\UpdateOrderStateToPacked;
use App\Actions\OrgAction;
use App\Actions\Traits\WithActionUpdate;
use App\Enums\Accounting\Invoice\InvoiceTypeEnum;
use App\Enums\Catalogue\Shop\ShopEngineEnum;
use App\Enums\Catalogue\Shop\ShopTypeEnum;
use App\Enums\Dispatching\DeliveryNote\DeliveryNoteStateEnum;
use App\Enums\Dispatching\DeliveryNote\DeliveryNoteTypeEnum;
use App\Enums\Ordering\Platform\PlatformTypeEnum;
use App\Models\Accounting\Invoice;
use App\Models\Accounting\InvoiceTransaction;
use App\Models\Dispatching\DeliveryNote;
use App\Models\Ordering\Order;
use App\Models\SysAdmin\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Lorisleiva\Actions\ActionRequest;

class UpdateDeliveryNoteStatePacked extends OrgAction
{
    use WithActionUpdate;
    use HasDeliveryNoteHydrators;

    private DeliveryNote $deliveryNote;
    protected User $user;


    /**
     * @throws \Throwable
     */
    public function handle(DeliveryNote $deliveryNote): DeliveryNote
    {
        if ($deliveryNote->state == DeliveryNoteStateEnum::PACKED) {
            return $deliveryNote;
        }

        if ($deliveryNote->hasBlockingItems()) {
            abort(422, __('Cannot pack: some items are waiting for a replacement decision or warehouse release'));
        }

        if (static::hasMissingParcelDimensions($deliveryNote)) {
            throw ValidationException::withMessages([
                'parcels' => __('Enter the dimensions of every parcel before setting as packed'),
            ]);
        }

        if ($missingBoxesMessage = DeliveryNoteBoxPackingList::make()->missingBoxesMessage($deliveryNote)) {
            throw ValidationException::withMessages(['boxes' => $missingBoxesMessage]);
        }

        $oldState = $deliveryNote->state;

        $returnsToFinalised = $this->returnsToFinalised($deliveryNote);
        $newState           = $returnsToFinalised ? DeliveryNoteStateEnum::FINALISED : DeliveryNoteStateEnum::PACKED;

        data_set($modelData, 'packed_at', now());
        data_set($modelData, 'packer_user_id', $this->user->id);
        data_set($modelData, 'state', $newState->value);


        $deliveryNote = DB::transaction(function () use ($deliveryNote, $modelData, $returnsToFinalised) {
            $notFullyPacked = $deliveryNote->deliveryNoteItems->reject(
                fn ($item) => UpdateDeliveryNoteItemPacking::isFullyPacked($item)
            );

            /*
             * These lines are swept up by one click on the note rather than confirmed one by one at
             * the bench, so they all carry the same done_at. They are flagged so that per line
             * packing rates can exclude them instead of reading a whole note as packed in an instant.
             */
            foreach ($notFullyPacked as $item) {
                StorePacking::make()->action($item, $this->user, [
                    'quantity' => UpdateDeliveryNoteItemPacking::quantityLeftToPack($item),
                    'data'     => ['auto_packed' => true],
                ]);
            }

            // Lock only the 'parcels' (handle concurrency)
            $currentParcels = DeliveryNote::whereKey($deliveryNote->id)
                ->lockForUpdate()
                ->value('parcels') ?? [];

            if (count($currentParcels) == 0) {
                $defaultParcel = [
                    [
                        'weight'     => $deliveryNote->effective_weight / 1000,
                        'dimensions' => [5, 5, 5]
                    ]
                ];

                data_set($modelData, 'parcels', $defaultParcel);
            }

            if ($deliveryNote->type != DeliveryNoteTypeEnum::REPLACEMENT) {
                if ($returnsToFinalised) {
                    UpdateOrderStateBackToFinalised::make()->action($deliveryNote->orders->first());
                } else {
                    UpdateOrderStateToPacked::make()->action($deliveryNote->orders->first(), $deliveryNote);
                }
            }


            $deliveryNote = $this->update($deliveryNote, $modelData);

            if ($deliveryNote->pickingSessions) {
                foreach ($deliveryNote->pickingSessions as $pickingSession) {
                    AutoFinishPackingPickingSession::run($pickingSession);
                }
            }

            foreach ($deliveryNote->trolleys as $trolley) {
                DB::table('delivery_note_has_trolleys')
                    ->where('delivery_note_id', $deliveryNote->id)->where('trolley_id', $trolley->id)->delete();
                $trolley->update(['current_delivery_note_id' => null]);
            }

            $order = $deliveryNote->orders->first();

            //Note: be careful if one day we delete these shipments in UnpackDeliveryNote
            if ($deliveryNote->is_shipping_by_external && $deliveryNote->shipments->isEmpty()) {
                if ($deliveryNote->shop->engine == ShopEngineEnum::FAIRE) {
                    StoreShipmentFromFaire::run($deliveryNote);
                } elseif ($order->platform->type == PlatformTypeEnum::TIKTOK) {
                    ProcessTiktokOrderShipment::run($order);
                }
            }


            return $deliveryNote;
        });

        $this->deliveryNoteHandlingHydrators($deliveryNote, $oldState);
        $this->deliveryNoteHandlingHydrators($deliveryNote, $newState);
        DeliveryNoteHydrateTrolleys::dispatch($deliveryNote->id);

        return $deliveryNote;
    }

    /**
     * A note that was finalised, unpacked and packed again goes straight back to finalised: its order
     * already carries the invoice, so finalising again is impossible and packed is a dead end (HELP-3153).
     *
     * @throws \Illuminate\Validation\ValidationException
     */
    protected function returnsToFinalised(DeliveryNote $deliveryNote): bool
    {
        if (!$deliveryNote->finalised_at) {
            return false;
        }

        if ($deliveryNote->type == DeliveryNoteTypeEnum::REPLACEMENT) {
            return true;
        }

        $order   = $deliveryNote->orders->first();
        $invoice = $order?->invoices()->where('type', InvoiceTypeEnum::INVOICE)->first();

        if (!$invoice) {
            return false;
        }

        $this->ensurePicksMatchInvoice($deliveryNote, $order, $invoice);

        return true;
    }

    /**
     * The invoice was made from the picks at finalisation. If they changed while the note was unpacked,
     * going back to finalised would ship something different from what was invoiced.
     *
     * @throws \Illuminate\Validation\ValidationException
     */
    protected function ensurePicksMatchInvoice(DeliveryNote $deliveryNote, Order $order, Invoice $invoice): void
    {
        $transactions = $order->transactions()->where('model_type', 'Product')->where('is_follow_on', false)->get();

        foreach ($transactions as $transaction) {
            $invoiceTransactionIds = InvoiceTransaction::where('invoice_id', $invoice->id)
                ->where('transaction_id', $transaction->id)
                ->pluck('id');

            $invoicedQuantity = (float)InvoiceTransaction::whereIn('id', $invoiceTransactionIds)->sum('quantity')
                + (float)InvoiceTransaction::whereIn('original_invoice_transaction_id', $invoiceTransactionIds)->where('is_tax_only', false)->where('in_process', false)->whereHas('invoice')->sum('quantity');

            $pickedQuantity = (float)GenerateInvoiceFromOrder::make()->recalculateTransactionTotals($transaction, $deliveryNote)['quantity'];

            if (abs($pickedQuantity - $invoicedQuantity) > 0.001) {
                throw ValidationException::withMessages([
                    'state' => __('The picked quantities no longer match invoice :invoice. Put the picks back to what was invoiced, or ask accounts to refund the difference first. How: :url', [
                        'invoice' => $invoice->reference,
                        'url'     => route('aiku-public.docs.show', 'repacking-a-finalised-delivery-note'),
                    ])
                ]);
            }
        }
    }


    /**
     * Parcel dimensions are obligatory, only dropshipping falls back to the 5x5x5 cm default parcel (HELP-3169).
     */
    public static function hasMissingParcelDimensions(DeliveryNote $deliveryNote): bool
    {
        if ($deliveryNote->shop->type == ShopTypeEnum::DROPSHIPPING) {
            return false;
        }

        $parcels = $deliveryNote->parcels ?? [];

        return empty($parcels) || collect($parcels)->contains(
            fn ($parcel) => collect(range(0, 2))->contains(fn ($index) => (float)data_get($parcel, "dimensions.$index") <= 0)
        );
    }

    /**
     * @throws \Throwable
     */
    public function asController(DeliveryNote $deliveryNote, ActionRequest $request): DeliveryNote
    {
        $this->user         = $request->user();
        $this->deliveryNote = $deliveryNote;
        $this->initialisationFromShop($deliveryNote->shop, $request);

        return $this->handle($deliveryNote);
    }


    /**
     * @throws \Throwable
     */
    public function action(DeliveryNote $deliveryNote, User $user): DeliveryNote
    {
        $this->user         = $user;
        $this->deliveryNote = $deliveryNote;
        $this->initialisationFromShop($deliveryNote->shop, []);

        return $this->handle($deliveryNote);
    }
}
