<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 20 Jun 2023 20:33:11 Malaysia Time, Pantai Lembeng, Bali, Indonesia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Actions\Ordering\Order;

use App\Actions\Dispatching\DeliveryNoteItem\StoreDeliveryNoteItem;
use App\Actions\Ordering\Transaction\StoreTransaction;
use App\Actions\Ordering\Transaction\UpdateTransaction;
use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\Ordering\WithOrderingEditAuthorisation;
use App\Actions\Traits\WithActionUpdate;
use App\Enums\Ordering\Order\OrderStateEnum;
use App\Models\Catalogue\Product;
use App\Models\Ordering\Order;
use App\Models\Ordering\Transaction;
use Carbon\Carbon;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\ActionRequest;

class SaveOrderModification extends OrgAction
{
    use WithActionUpdate;
    use HasOrderHydrators;
    use WithOrderingEditAuthorisation;

    private Order $order;

    public function handle(Order $order, array $modelData): Order
    {
        return DB::transaction(function () use ($order, $modelData) {
            if (Arr::has($modelData, 'transactions')) {
                $transactions = Arr::get($modelData, 'transactions');
                foreach ($transactions as $key => $data) {
                    $transaction = Transaction::find($key);
                    UpdateTransaction::make()->action($transaction, [
                        'quantity_ordered' => Arr::get($data, 'newQty')
                    ]);

                    if ($order->state == OrderStateEnum::IN_WAREHOUSE && $order->deliveryNotes()->exists()) {
                        //TODO:  do smthn for the dn items
                    }
                }
            }

            if (Arr::has($modelData, 'products')) {
                $products = Arr::get($modelData, 'products');
                foreach ($products as $key => $data) {
                    $product = Product::find($key);
                    $transaction = StoreTransaction::make()->action($order, $product->currentHistoricProduct, [
                        'quantity_ordered' => Arr::get($data, 'quantity_ordered')
                    ]);

                    if ($order->state == OrderStateEnum::IN_WAREHOUSE && $order->deliveryNotes()->exists()) {
                        $deliveryNote = $order->deliveryNotes->first();
                        foreach ($product->orgStocks as $orgStock) {
                            $quantity             = $orgStock->pivot->quantity * $transaction->quantity_ordered;
                            $deliveryNoteItemData = [
                                'org_stock_id'      => $orgStock->id,
                                'transaction_id'    => $transaction->id,
                                'quantity_required' => $quantity,
                                'original_quantity_required' => $quantity
                            ];
                            StoreDeliveryNoteItem::make()->action($deliveryNote, $deliveryNoteItemData);
                        }
                    }
                }
            }

            $this->orderHydrators($order);

            $modificationData = [
                'date_time' => Carbon::now()->toDateTimeString(),
                'modified_by' => request()->user()->username,
                'data' => $modelData
            ];

            $modifications = $order->post_submit_modification_data ?? [];
            array_push($modifications, $modificationData);

            $this->update($order, [
                'post_submit_modification_data' => $modifications
            ]);

            return $order;
        });
    }

    public function rules(): array
    {
        return [
            'transactions' => ['sometimes', 'array'],
            'products' => ['sometimes', 'array']
        ];
    }

    /**
     * @throws \Throwable
     */
    public function asController(Order $order, ActionRequest $request): Order
    {
        $this->order = $order;
        $this->initialisationFromShop($order->shop, $request);

        return $this->handle($order, $this->validatedData);
    }
}
