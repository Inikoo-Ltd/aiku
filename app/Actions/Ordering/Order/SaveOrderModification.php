<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 20 Jun 2023 20:33:11 Malaysia Time, Pantai Lembeng, Bali, Indonesia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Actions\Ordering\Order;

use App\Actions\Dispatching\DeliveryNote\WithDeliveryNoteQuantitySync;
use App\Actions\Dispatching\DeliveryNoteItem\StoreDeliveryNoteItem;
use App\Actions\Ordering\Transaction\StoreTransaction;
use App\Actions\Ordering\Transaction\UpdateTransactionProductQuantityOrdered;
use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\Ordering\WithOrderingEditAuthorisation;
use App\Actions\Traits\WithActionUpdate;
use App\Enums\Catalogue\Shop\ShopTypeEnum;
use App\Enums\Dispatching\DeliveryNote\DeliveryNoteStateEnum;
use App\Enums\Dispatching\DeliveryNote\DeliveryNoteTypeEnum;
use App\Enums\Dispatching\DeliveryNoteItem\DeliveryNoteItemStateEnum;
use App\Enums\Ordering\Order\OrderStateEnum;
use App\Enums\Ordering\Platform\PlatformTypeEnum;
use App\Enums\Ordering\Transaction\TransactionStateEnum;
use App\Enums\Ordering\Transaction\TransactionStatusEnum;
use App\Models\Catalogue\Product;
use App\Models\Dispatching\DeliveryNote;
use App\Models\Ordering\Order;
use App\Models\Ordering\Transaction;
use App\Models\SysAdmin\User;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\ActionRequest;

class SaveOrderModification extends OrgAction
{
    use WithActionUpdate;
    use HasOrderHydrators;
    use WithOrderingEditAuthorisation;
    use WithDeliveryNoteQuantitySync;

    /**
     * A new line can join a delivery note for as long as somebody is still walking the warehouse
     * for it. Once the note is picked the money on the order follows the picks and the parcel is
     * being packed, so from there the extra items go on a follow-up order instead.
     */
    public const DELIVERY_NOTE_STATES_ACCEPTING_NEW_LINES = [
        DeliveryNoteStateEnum::UNASSIGNED,
        DeliveryNoteStateEnum::QUEUED,
        DeliveryNoteStateEnum::HANDLING,
        DeliveryNoteStateEnum::HANDLING_BLOCKED,
    ];

    public static function isEditedInAiku(Order $order): bool
    {
        return $order->shop->type != ShopTypeEnum::EXTERNAL
            && (!$order->platform || $order->platform->type == PlatformTypeEnum::MANUAL)
            && !$order->isLockedInAurora();
    }

    public static function acceptsNewProducts(Order $order): bool
    {
        return self::isEditedInAiku($order)
            && in_array($order->state, [OrderStateEnum::SUBMITTED, OrderStateEnum::IN_WAREHOUSE, OrderStateEnum::HANDLING, OrderStateEnum::HANDLING_BLOCKED]);
    }

    /**
     * @throws \Throwable
     */
    public function handle(Order $order, array $modelData, User $user): Order
    {
        if ($order->shop->type == ShopTypeEnum::EXTERNAL) {
            abort(422, __('Orders of external shops follow the external platform data and cannot be modified here'));
        }

        if ($order->platform && $order->platform->type != PlatformTypeEnum::MANUAL) {
            abort(422, __('Platform orders cannot be modified here'));
        }

        if (in_array($order->state, [OrderStateEnum::CANCELLED, OrderStateEnum::FINALISED, OrderStateEnum::DISPATCHED])) {
            abort(422, __('This order can no longer be modified'));
        }

        foreach (Arr::get($modelData, 'transactions', []) as $transactionId => $data) {
            $this->changeQuantity($order->transactions()->findOrFail($transactionId), (float)Arr::get($data, 'newQty'), $user);
        }

        $products = Arr::get($modelData, 'products', []);
        if ($products) {
            DB::transaction(function () use ($order, $products, $user) {
                $deliveryNote = $this->lockDeliveryNoteAcceptingNewLines($order);

                foreach ($products as $productId => $data) {
                    $product  = Product::where('shop_id', $order->shop_id)->with('orgStocks')->findOrFail($productId);
                    $quantity = (float)Arr::get($data, 'quantity_ordered');

                    $existingTransaction = $order->transactions()
                        ->where('model_type', class_basename(Product::class))
                        ->where('model_id', $product->id)
                        ->where('is_gift', false)
                        ->first();

                    if ($existingTransaction) {
                        $this->changeQuantity($existingTransaction, (float)$existingTransaction->quantity_ordered + $quantity, $user);

                        continue;
                    }

                    $transaction = StoreTransaction::make()->action($order, $product->currentHistoricProduct, [
                        'quantity_ordered' => $quantity
                    ]);

                    if ($deliveryNote) {
                        $this->addLineToDeliveryNote($deliveryNote, $transaction, $product, $user);
                    }
                }
            });
        }

        $order->refresh();
        UpdateOrderPaymentsStatus::run($order);
        $this->orderHydrators($order);

        $modifications   = $order->post_submit_modification_data ?? [];
        $modifications[] = [
            'date_time'   => Carbon::now()->toDateTimeString(),
            'modified_by' => $user->username,
            'data'        => $modelData
        ];

        $this->update($order, [
            'post_submit_modification_data' => $modifications
        ]);

        return $order;
    }

    /**
     * @throws \Throwable
     */
    protected function changeQuantity(Transaction $transaction, float $quantity, User $user): void
    {
        UpdateTransactionProductQuantityOrdered::make()->action($transaction, [
            'quantity_ordered' => $quantity
        ], $user);
    }

    /**
     * The note is locked and its state read again inside the transaction, so a picker pressing
     * "picked" at the same moment cannot leave a line on the note that nobody will walk to.
     */
    protected function lockDeliveryNoteAcceptingNewLines(Order $order): ?DeliveryNote
    {
        $deliveryNote = $order->deliveryNotes()
            ->where('type', DeliveryNoteTypeEnum::ORDER)
            ->whereNot('state', DeliveryNoteStateEnum::CANCELLED)
            ->lockForUpdate()
            ->first();

        if ($deliveryNote && !in_array($deliveryNote->state, self::DELIVERY_NOTE_STATES_ACCEPTING_NEW_LINES)) {
            abort(409, __('The warehouse has already finished picking this order, create a follow-up order for the extra items'));
        }

        return $deliveryNote;
    }

    protected function addLineToDeliveryNote(DeliveryNote $deliveryNote, Transaction $transaction, Product $product, User $user): void
    {
        $transaction->update([
            'state'           => TransactionStateEnum::IN_WAREHOUSE,
            'status'          => TransactionStatusEnum::PROCESSING,
            'in_warehouse_at' => now()
        ]);

        $beingPicked = in_array($deliveryNote->state, [DeliveryNoteStateEnum::HANDLING, DeliveryNoteStateEnum::HANDLING_BLOCKED]);

        foreach ($product->orgStocks as $orgStock) {
            $quantity         = $orgStock->pivot->quantity * ($transaction->quantity_ordered + $transaction->quantity_bonus);
            $deliveryNoteItem = StoreDeliveryNoteItem::make()->action($deliveryNote, [
                'org_stock_id'               => $orgStock->id,
                'transaction_id'             => $transaction->id,
                'quantity_required'          => $quantity,
                'original_quantity_required' => $quantity
            ]);

            if ($beingPicked) {
                $deliveryNoteItem->update(['state' => DeliveryNoteItemStateEnum::HANDLING]);
            }
        }

        $this->walkDeliveryNoteBackToPicking($deliveryNote->refresh(), true, false, $user);
    }

    /**
     * @return array{reference: string, total_amount: string, pay_status: ?string}
     */
    public function jsonResponse(Order $order): array
    {
        return [
            'reference'    => $order->reference,
            'total_amount' => $order->total_amount,
            'pay_status'   => $order->pay_status?->value,
        ];
    }

    public function htmlResponse(): RedirectResponse
    {
        return back();
    }

    public function rules(): array
    {
        return [
            'transactions'                => ['sometimes', 'array'],
            'transactions.*.newQty'       => ['required', 'numeric', 'min:0'],
            'products'                    => ['sometimes', 'array'],
            'products.*.quantity_ordered' => ['required', 'numeric', 'gt:0'],
        ];
    }

    /**
     * @throws \Throwable
     */
    public function asController(Order $order, ActionRequest $request): Order
    {
        $this->initialisationFromShop($order->shop, $request);

        return $this->handle($order, $this->validatedData, $request->user());
    }

    /**
     * @throws \Throwable
     */
    public function action(Order $order, array $modelData, User $user): Order
    {
        $this->asAction = true;
        $this->initialisationFromShop($order->shop, $modelData);

        return $this->handle($order, $this->validatedData, $user);
    }
}
