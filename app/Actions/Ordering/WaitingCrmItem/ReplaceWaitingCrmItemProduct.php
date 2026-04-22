<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 16 Apr 2026 14:00:43 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Ordering\WaitingCrmItem;

use App\Actions\Dispatching\DeliveryNote\Hydrators\DeliveryNoteHydrateWaitingItems;
use App\Actions\Dispatching\DeliveryNoteItem\StoreDeliveryNoteItem;
use App\Actions\Dispatching\Picking\StoreNotPickPicking;
use App\Actions\Ordering\Transaction\StoreTransaction;
use App\Actions\OrgAction;
use App\Enums\Ordering\Transaction\TransactionStateEnum;
use App\Enums\Ordering\Transaction\TransactionStatusEnum;
use App\Models\Catalogue\Product;
use App\Models\Dispatching\DeliveryNoteItem;
use App\Models\SysAdmin\User;
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


            $deliveryNoteItem->update([
                'quantity_waiting_crm' => 0,
                'has_waiting_crm'      => false,
            ]);
            DeliveryNoteHydrateWaitingItems::run($deliveryNoteItem->delivery_note_id);

            StoreNotPickPicking::make()->action(
                $deliveryNoteItem,
                $user,
                [
                    'quantity' => $crmWaitingQuantity
                ]
            );


            foreach ($modelData['products'] as $productData) {
                $product = Product::find($productData['id']);
                if ($product) {
                    $transaction = StoreTransaction::make()->action(
                        order: $order,
                        historicAsset: $product->currentHistoricProduct,
                        modelData: [
                            'quantity_ordered' => $productData['quantity'],
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

                            $deliveryNoteItem = StoreDeliveryNoteItem::make()->action($deliveryNoteItem->deliveryNote, $deliveryNoteItemData);
                            $deliveryNoteItem->update([
                                'quantity_waiting_warehouse' => $quantity,
                                'has_waiting_warehouse'      => true,
                            ]);
                            DeliveryNoteHydrateWaitingItems::run($deliveryNoteItem->delivery_note_id);
                        }
                    }
                }
            }
        });
    }

    public function rules(): array
    {
        return [
            'products'            => ['required', 'array', 'min:1'],
            'products.*.id'       => ['required', 'integer', 'exists:products,id'],
            'products.*.quantity' => ['required', 'numeric', 'min:0'],
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
