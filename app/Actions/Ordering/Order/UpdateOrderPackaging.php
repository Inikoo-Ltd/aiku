<?php

/*
 * Author: Andi Ferdiawan
 * Created: Fri, 10 Jul 2026 22:30:00 Central Indonesia Time, Bali, Indonesia
 * Copyright (c) 2026, Inikoo Ltd
 */

namespace App\Actions\Ordering\Order;

use App\Actions\Ordering\Transaction\DestroyTransaction;
use App\Actions\Ordering\Transaction\StoreTransactionFromLeaflet;
use App\Actions\Ordering\Transaction\StoreTransactionFromPackaging;
use App\Actions\OrgAction;
use App\Enums\Catalogue\Packaging\PackagingStateEnum;
use App\Models\Billables\Leaflet;
use App\Models\Billables\Packaging;
use App\Models\Ordering\Order;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Lorisleiva\Actions\ActionRequest;

class UpdateOrderPackaging extends OrgAction
{
    public function handle(Order $order, array $modelData): Order
    {
        DB::transaction(function () use ($order, $modelData) {
            $packagingId = Arr::get($modelData, 'packaging_id');
            $leafletIds  = array_values(array_map('intval', Arr::get($modelData, 'leaflet_ids', [])));

            $this->syncPackaging($order, $packagingId);
            $this->syncLeaflets($order, $leafletIds);

            $orderData = [
                'packaging_id' => $packagingId,
                'insert_types' => $leafletIds,
            ];

            if (Arr::has($modelData, 'personalised_message')) {
                $orderData['personalised_message'] = Arr::get($modelData, 'personalised_message');
            }

            $order->update($orderData);
        });

        return $order;
    }

    private function syncPackaging(Order $order, ?int $packagingId): void
    {
        foreach ($order->transactions()->where('model_type', 'Packaging')->get() as $transaction) {
            if ($transaction->model_id != $packagingId) {
                DestroyTransaction::run($transaction);
            }
        }

        if (!$packagingId) {
            return;
        }

        $exists = $order->transactions()
            ->where('model_type', 'Packaging')
            ->where('model_id', $packagingId)
            ->exists();

        if ($exists) {
            return;
        }

        $packaging = Packaging::find($packagingId);

        StoreTransactionFromPackaging::run($order, $packaging, [
            'quantity_ordered' => 1,
            'gross_amount'     => $packaging->price,
            'net_amount'       => $packaging->price,
        ]);
    }

    /** @param array<int, int> $leafletIds */
    private function syncLeaflets(Order $order, array $leafletIds): void
    {
        foreach ($order->transactions()->where('model_type', 'Leaflet')->get() as $transaction) {
            if (!in_array($transaction->model_id, $leafletIds)) {
                DestroyTransaction::run($transaction);
            }
        }

        foreach ($leafletIds as $leafletId) {
            $exists = $order->transactions()
                ->where('model_type', 'Leaflet')
                ->where('model_id', $leafletId)
                ->exists();

            if ($exists) {
                continue;
            }

            $leaflet = Leaflet::find($leafletId);

            StoreTransactionFromLeaflet::run($order, $leaflet, [
                'quantity_ordered' => 1,
                'gross_amount'     => $leaflet->price,
                'net_amount'       => $leaflet->price,
            ]);
        }
    }

    public function rules(): array
    {
        return [
            'packaging_id'   => [
                'sometimes',
                'nullable',
                Rule::exists('packagings', 'id')
                    ->where('shop_id', $this->shop->id)
                    ->where('state', PackagingStateEnum::ACTIVE->value),
            ],
            'leaflet_ids'    => ['sometimes', 'array'],
            'leaflet_ids.*'  => [
                'integer',
                Rule::exists('leaflets', 'id')->where('shop_id', $this->shop->id),
            ],
            'personalised_message' => ['sometimes', 'nullable', 'string', 'max:200'],
        ];
    }

    public function action(Order $order, array $modelData): Order
    {
        $this->asAction = true;
        $this->initialisationFromShop($order->shop, $modelData);

        return $this->handle($order, $this->validatedData);
    }

    public function asController(Order $order, ActionRequest $request): Order
    {
        $this->initialisationFromShop($order->shop, $request);

        return $this->handle($order, $this->validatedData);
    }
}
