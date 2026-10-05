<?php

/*
 * Author: Andi Ferdiawan
 * Created: Fri, 10 Jul 2026 22:00:00 Central Indonesia Time, Bali, Indonesia
 * Copyright (c) 2026, Inikoo Ltd
 */

namespace App\Actions\Ordering\Transaction;

use App\Actions\Ordering\Order\CalculateOrderTotalAmounts;
use App\Actions\Ordering\Order\Hydrators\OrderHydrateTransactions;
use App\Actions\OrgAction;
use App\Actions\Traits\WithOrderExchanges;
use App\Actions\Traits\WithStoreNoProductTransaction;
use App\Enums\Ordering\Transaction\TransactionStateEnum;
use App\Enums\Ordering\Transaction\TransactionStatusEnum;
use App\Models\Billables\Leaflet;
use App\Models\Ordering\Order;
use App\Models\Ordering\Transaction;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreTransactionFromLeaflet extends OrgAction
{
    use WithOrderExchanges;
    use WithNoProductStoreTransaction;
    use WithStoreNoProductTransaction;

    private ?Leaflet $leaflet;

    public function handle(Order $order, ?Leaflet $leaflet, array $modelData): Transaction
    {
        $modelData = $this->prepareLeafletTransaction($leaflet, $modelData);
        $modelData = $this->transactionFieldProcess($order, $modelData);

        /** @var Transaction $transaction */
        $transaction = $order->transactions()->create($modelData);

        if ($this->strict) {
            CalculateOrderTotalAmounts::run($order);
            OrderHydrateTransactions::dispatch($order);
        }

        return $transaction;
    }

    public function rules(): array
    {
        return [
            'state'               => ['sometimes', Rule::enum(TransactionStateEnum::class)],
            'status'              => ['sometimes', Rule::enum(TransactionStatusEnum::class)],
            'gross_amount'        => ['sometimes', 'numeric'],
            'net_amount'          => ['sometimes', 'numeric'],
            'org_exchange'        => ['sometimes', 'numeric'],
            'grp_exchange'        => ['sometimes', 'numeric'],
            'org_net_amount'      => ['sometimes', 'numeric'],
            'grp_net_amount'      => ['sometimes', 'numeric'],
            'tax_category_id'     => ['sometimes', 'required', 'exists:tax_categories,id'],
            'date'                => ['sometimes', 'required', 'date'],
            'quantity_ordered'    => ['required', 'numeric', 'min:0'],
            'quantity_dispatched' => ['sometimes', 'required', 'numeric', 'min:0'],
            'submitted_at'        => ['sometimes', 'required', 'date'],
        ];
    }

    public function afterValidator(Validator $validator): void
    {
        if ($this->leaflet && $this->leaflet->shop_id != $this->shop->id) {
            $validator->errors()->add('leaflet', 'Leaflet does not belong to this shop');
        }
    }

    public function action(Order $order, ?Leaflet $leaflet, array $modelData, bool $strict = true): Transaction
    {
        $this->strict  = $strict;
        $this->leaflet = $leaflet;
        $this->initialisationFromShop($order->shop, $modelData);

        return $this->handle($order, $leaflet, $this->validatedData);
    }
}
