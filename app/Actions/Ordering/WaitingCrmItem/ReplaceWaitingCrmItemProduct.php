<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 16 Apr 2026 14:00:43 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Ordering\WaitingCrmItem;

use App\Actions\Dispatching\DeliveryNote\Hydrators\DeliveryNoteHydrateWaitingItems;
use App\Actions\Dispatching\DeliveryNoteItem\CalculateDeliveryNoteItemTotalPicked;
use App\Actions\Dispatching\DeliveryNoteItem\StoreDeliveryNoteItem;
use App\Actions\Dispatching\Picking\StoreNotPickPicking;
use App\Actions\Ordering\Order\CalculateOrderDiscounts;
use App\Actions\Ordering\Transaction\StoreTransaction;
use App\Actions\OrgAction;
use App\Enums\Dispatching\DeliveryNoteItem\DeliveryNoteItemStateEnum;
use App\Enums\Ordering\Transaction\TransactionStateEnum;
use App\Enums\Ordering\Transaction\TransactionStatusEnum;
use App\Models\Catalogue\Product;
use App\Models\Dispatching\DeliveryNoteItem;
use App\Models\SysAdmin\User;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\ActionRequest;

class ReplaceWaitingCrmItemProduct extends OrgAction
{
    /**
     * @throws \Throwable
     */
    public function handle(DeliveryNoteItem $deliveryNoteItem, User $user, array $modelData): void
    {
        /** @var \App\Models\Ordering\Order $order */
        $order = $deliveryNoteItem->deliveryNote->orders()->first();
        if (!$order) {
            abort(404);
        }


        DB::transaction(function () use ($deliveryNoteItem, $modelData, $order, $user) {
            $crmWaitingQuantity = $deliveryNoteItem->quantity_waiting_crm;

            /*
             * A stock packed in an outer can be replaced a fraction of a pack at a time: swapping
             * 2 of a 16-pack leaves 14/16 still waiting for CRM, to be replaced, not picked or sent
             * back to the warehouse in a later decision. Without a quantity the whole lot goes.
             */
            $replacedQuantity = $this->resolveQuantityInPacks(
                Arr::get($modelData, 'quantity'),
                Arr::get($modelData, 'units'),
                $deliveryNoteItem->orgStock?->packed_in
            );

            if ($replacedQuantity === null || $replacedQuantity <= 0 || $replacedQuantity > $crmWaitingQuantity) {
                $replacedQuantity = $crmWaitingQuantity;
            }

            $remainingQuantity = round($crmWaitingQuantity - $replacedQuantity, 6);

            $deliveryNoteItem->update([
                'quantity_waiting_crm' => $remainingQuantity,
                'has_waiting_crm'      => $remainingQuantity > 0,
            ]);
            DeliveryNoteHydrateWaitingItems::run($deliveryNoteItem->delivery_note_id);

            StoreNotPickPicking::make()->action(
                $deliveryNoteItem,
                $user,
                [
                    'quantity' => $replacedQuantity
                ]
            );


            foreach ($modelData['products'] as $productData) {
                $product = Product::find($productData['id']);
                if ($product) {
                    /*
                     * The replacement moves in cuts too: 4 of a 16-pack of the new product answers
                     * 4 of a 16-pack of the old one, so the line is ordered as a quarter of a pack.
                     */
                    $quantityOrdered = $this->resolveQuantityInPacks(
                        Arr::get($productData, 'quantity'),
                        Arr::get($productData, 'units'),
                        $product->units
                    );

                    if ($quantityOrdered === null || $quantityOrdered <= 0) {
                        continue;
                    }

                    $transaction = StoreTransaction::make()->action(
                        order: $order,
                        historicAsset: $product->currentHistoricProduct,
                        modelData: [
                            'quantity_ordered' => $quantityOrdered,
                            'state'            => TransactionStateEnum::HANDLING_BLOCKED,
                            'status'           => TransactionStatusEnum::PROCESSING,
                            'submitted_at'     => now()
                        ]
                    );

                    foreach ($product->orgStocks as $orgStock) {
                        $quantity = $orgStock->pivot->quantity * ($transaction->quantity_ordered + $transaction->quantity_bonus);
                        if ($quantity > 0) {
                            $deliveryNoteItemData = [
                                'org_stock_id'               => $orgStock->id,
                                'transaction_id'             => $transaction->id,
                                'quantity_required'          => $quantity,
                                'original_quantity_required' => $quantity,
                            ];

                            $replacementDeliveryNoteItem = StoreDeliveryNoteItem::make()->action($deliveryNoteItem->deliveryNote, $deliveryNoteItemData);
                            $replacementDeliveryNoteItem->update([
                                'state'                      => DeliveryNoteItemStateEnum::HANDLING_BLOCKED,
                                'quantity_waiting_warehouse' => $quantity,
                                'has_waiting_warehouse'      => true,
                            ]);
                            DeliveryNoteHydrateWaitingItems::run($replacementDeliveryNoteItem->delivery_note_id);
                            CalculateDeliveryNoteItemTotalPicked::run($replacementDeliveryNoteItem);
                        }
                    }
                }
            }
        });

        /**
         * The replacement line is stored at full price: StoreTransaction's totals pass skips the
         * discount recalculation for orders past submission. Run it explicitly so the new line gets
         * the discounts the customer is entitled to; CalculateOrderDiscounts prices post-submission
         * orders against the offers valid at submission time, so this cannot alter the other lines.
         */
        CalculateOrderDiscounts::run($order->refresh());
    }

    /**
     * Quantities are stored in packs. A cut is sent as a count of single items instead, leaving the
     * pack size to the server so a stale page cannot price a swap against the wrong outer.
     */
    private function resolveQuantityInPacks(float|int|null $quantity, float|int|null $units, float|int|null $packedIn): ?float
    {
        if ($units !== null) {
            $packedIn = (float)($packedIn ?: 1);

            return $packedIn > 0 ? round($units / $packedIn, 6) : null;
        }

        return $quantity === null ? null : (float)$quantity;
    }

    public function rules(): array
    {
        return [
            'quantity'            => ['sometimes', 'nullable', 'numeric', 'gt:0'],
            'units'               => ['sometimes', 'nullable', 'integer', 'gt:0'],
            'products'            => ['required', 'array', 'min:1'],
            'products.*.id'       => ['required', 'integer', 'exists:products,id'],
            'products.*.quantity' => ['required_without:products.*.units', 'numeric', 'min:0'],
            'products.*.units'    => ['required_without:products.*.quantity', 'integer', 'min:0'],
        ];
    }

    /**
     * @throws \Throwable
     */
    public function asController(DeliveryNoteItem $deliveryNoteItem, ActionRequest $request): void
    {
        $this->initialisationFromShop($deliveryNoteItem->deliveryNote->shop, $request);

        $this->handle($deliveryNoteItem, $request->user(), $this->validatedData);
    }
}
