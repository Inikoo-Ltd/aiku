<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 20 Jun 2023 20:33:12 Malaysia Time, Pantai Lembeng, Bali, Indonesia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Actions\Ordering\Order;

use App\Actions\Catalogue\Shop\External\Faire\UpdateFaireOrder;
use App\Actions\Ordering\Order\Hydrators\OrderHydrateTransactions;
use App\Actions\Ordering\Transaction\UpdateTransaction;
use App\Actions\OrgAction;
use App\Actions\Traits\Rules\WithNoStrictRules;
use App\Actions\Traits\WithActionUpdate;
use App\Actions\Traits\WithFixedAddressActions;
use App\Actions\Traits\WithModelAddressActions;
use App\Actions\Traits\WithOrderExchanges;
use App\Enums\Catalogue\Shop\ShopEngineEnum;
use App\Enums\Dispatching\DeliveryNote\DeliveryNoteStateEnum;
use App\Enums\Ordering\Order\OrderShippingEngineEnum;
use App\Enums\Ordering\Order\OrderStateEnum;
use App\Enums\Ordering\Order\OrderStatusEnum;
use App\Enums\Ordering\Transaction\TransactionStateEnum;
use App\Enums\Ordering\Transaction\TransactionStatusEnum;
use App\Models\Ordering\Order;
use App\Models\Ordering\Transaction;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Arr;
use Lorisleiva\Actions\ActionRequest;
use App\Actions\Traits\Authorisations\Ordering\WithOrderEditAuthorisation;

class UpdateOrderShippingEngineAsManual extends OrgAction
{
    use WithActionUpdate;
    use WithFixedAddressActions;
    use WithModelAddressActions;
    use HasOrderHydrators;
    use WithNoStrictRules;
    use WithOrderExchanges;
    use WithOrderEditAuthorisation;

    private Order $order;

    public function handle(Order $order, array $modelData): Order
    {
        $shippingTransaction = $order->transactions()->where('model_type', 'ShippingZone')->first();
        if ($shippingTransaction) {
            $this->updateShippingTransaction($shippingTransaction, Arr::get($modelData, 'shipping_amount'));
        } else {
            $this->storeShippingTransaction($order, Arr::get($modelData, 'shipping_amount'));
        }

        $order->update(
            [
                'shipping_engine' => OrderShippingEngineEnum::MANUAL,
                'shipping_amount' => Arr::get($modelData, 'shipping_amount'),
            ]
        );


        CalculateOrderTotalAmounts::run($order);

        if (in_array($order->state, [OrderStateEnum::FINALISED, OrderStateEnum::DISPATCHED])) {
            UpdateOrderPaymentsStatus::run($order);
        }

        return $order;
    }

    private function storeShippingTransaction(Order $order, $shippingAmount): Transaction
    {
        $modelData = [];
        data_set($modelData, 'tax_category_id', $order->tax_category_id);
        data_set($modelData, 'model_type', 'ShippingZone');


        $net   = $shippingAmount;
        $gross = $shippingAmount;


        data_set($modelData, 'shop_id', $order->shop_id);
        data_set($modelData, 'customer_id', $order->customer_id);
        data_set($modelData, 'group_id', $order->group_id);
        data_set($modelData, 'organisation_id', $order->organisation_id);

        data_set($modelData, 'date', now(), overwrite: false);
        data_set($modelData, 'submitted_at', $order->submitted_at);
        data_set($modelData, 'gross_amount', $gross ?? 0);
        data_set($modelData, 'net_amount', $net ?? 0);

        $status = match ($order->status) {
            OrderStatusEnum::CREATING => TransactionStatusEnum::CREATING,
            OrderStatusEnum::PROCESSING => TransactionStatusEnum::PROCESSING,
            default => TransactionStatusEnum::SETTLED,
        };

        $state = match ($order->state) {
            OrderStateEnum::SUBMITTED => TransactionStateEnum::SUBMITTED,
            OrderStateEnum::IN_WAREHOUSE => TransactionStateEnum::IN_WAREHOUSE,
            OrderStateEnum::HANDLING => TransactionStateEnum::HANDLING,
            OrderStateEnum::PACKED => TransactionStateEnum::PACKED,
            OrderStateEnum::FINALISED => TransactionStateEnum::FINALISED,
            OrderStateEnum::DISPATCHED => TransactionStateEnum::DISPATCHED,
            OrderStateEnum::CANCELLED => TransactionStateEnum::CANCELLED,

            default => TransactionStateEnum::CREATING,
        };


        data_set($modelData, 'state', $state);
        data_set($modelData, 'status', $status);


        if ($order->state == OrderStateEnum::SUBMITTED) {
            data_set($modelData, 'submitted_at', now(), overwrite: false);
        }


        $modelData = $this->processExchanges($modelData, $order->shop);


        /** @var Transaction $transaction */
        $transaction = $order->transactions()->create($modelData);
        OrderHydrateTransactions::dispatch($order);

        return $transaction;
    }


    private function updateShippingTransaction(Transaction $transaction, $shippingAmount): Transaction
    {
        return UpdateTransaction::run(
            $transaction,
            [
                'model_id'          => null,
                'asset_id'          => null,
                'historic_asset_id' => null,
                'gross_amount'      => $shippingAmount ?? 0,
                'net_amount'        => $shippingAmount ?? 0,
            ],
            false
        );
    }

    public function rules(): array
    {
        return [
            'shipping_amount' => ['required', 'numeric', 'min:0'],
        ];
    }

    public function afterValidator(Validator $validator): void
    {
        $isFaire        = $this->order->shop->engine == ShopEngineEnum::FAIRE;
        $shippingAmount = Arr::get($validator->getData(), 'shipping_amount');
        if ($isFaire && $this->order->is_shipping_by_external && is_numeric($shippingAmount) && $faireVatMismatch = $this->getFaireShippingVatMismatch((float)$shippingAmount)) {
            $validator->errors()->add('message', $faireVatMismatch);
        } elseif ($isFaire && !$this->order->is_shipping_by_external && $this->order->deliveryNotes()->where('state', '!=', DeliveryNoteStateEnum::CANCELLED)->whereHas('shipments')->exists()) {
            $validator->errors()->add('message', __('The tracking of this order was already sent to Faire. Enter the shipping cost on Faire and it is copied here.'));
        } elseif ($this->order->state == OrderStateEnum::CANCELLED) {
            $validator->errors()->add('message', __('Shipping can not be changed once order is cancelled.'));
        } elseif (in_array($this->order->state, [OrderStateEnum::DISPATCHED, OrderStateEnum::FINALISED]) && $this->order->invoices()->exists()) {
            $validator->errors()->add('message', __('Shipping can not be changed once order is invoiced.'));
        } elseif ($this->order->sharedBillingInvoice()) {
            $validator->errors()->add('message', __('Shipping can not be changed once order is invoiced.'));
        }
    }

    /**
     * Faire does not say what it charged for shipping on its own label, only the VAT on it,
     * so the amount typed must carry that VAT. When Faire states none, can not be reached, or adds
     * recargo de equivalencia (which may or may not reach the shipping), the amount is not checked.
     */
    private function getFaireShippingVatMismatch(float $shippingAmount): ?string
    {
        $vatRate = (float)$this->order->taxCategory?->rate;
        if ($vatRate <= 0) {
            return null;
        }

        try {
            $faireOrder = $this->order->shop->getFaireOrder((string)$this->order->external_id);
        } catch (ConnectionException) {
            return null;
        }

        $faireTaxes = collect(Arr::get($faireOrder, 'payout_costs.taxes', []));
        if ($faireTaxes->contains('tax_type', 'RECARGO')) {
            return null;
        }

        $shippingTaxes = $faireTaxes->where('taxable_item_type', 'SHIPPING')->values()->all();
        $shippingVat   = UpdateFaireOrder::make()->getFaireTaxAmount(['payout_costs' => ['taxes' => $shippingTaxes]], $this->order->shop);

        if ($shippingVat < 0.01 || abs($shippingAmount * $vatRate - $shippingVat) < 0.01) {
            return null;
        }

        return __('Faire charged :vat VAT on the shipping of this order, which is a shipping of about :shipping, not :typed. Check the shipping on the Faire invoice.', [
            'vat'      => number_format($shippingVat, 2),
            'shipping' => number_format($shippingVat / $vatRate, 2),
            'typed'    => number_format($shippingAmount, 2),
        ]);
    }

    public function asController(Order $order, ActionRequest $request): Order
    {
        $this->order = $order;
        $this->initialisationFromShop($order->shop, $request);

        return $this->handle($order, $this->validatedData);
    }
}
