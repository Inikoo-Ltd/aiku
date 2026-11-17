<?php

/*
 * Author Louis Perez
 * Created on 17-11-2026-09h-40m
 * GitHub: https://github.com/louis-perez
 * Copyright 2026
*/

namespace App\Actions\Api\Group\Fulfilment;

use App\Enums\Fulfilment\Pallet\PalletStateEnum;
use App\Enums\Fulfilment\PalletDelivery\PalletDeliveryStateEnum;
use App\Models\Fulfilment\Pallet;
use App\Models\Fulfilment\PalletDelivery;
use App\Models\Inventory\Warehouse;
use Illuminate\Database\Eloquent\Builder;
use Lorisleiva\Actions\Concerns\AsObject;

class ScanMobileFulfilmentCode
{
    use AsObject;
    use WithMobileFulfilment;

    public const string DELIVERY_BARCODE_PREFIX = 'pad-';

    /**
     * @return array{type: string, pallet?: array<string, mixed>, pallet_delivery?: array<string, mixed>}|null
     */
    public function handle(Warehouse $warehouse, string $code): ?array
    {
        if ($pallet = $this->scannedPallet($warehouse, $code)) {
            return ['type' => 'pallet', 'pallet' => [
                'id'                 => $pallet->id,
                'reference'          => $pallet->reference,
                'customer_reference' => $pallet->customer_reference,
                'state'              => $pallet->state->value,
                'state_label'        => PalletStateEnum::labels()[$pallet->state->value],
                'status'             => $pallet->status->value,
                'location_code'      => $pallet->location?->code,
            ]];
        }

        if ($palletDelivery = $this->scannedPalletDelivery($warehouse, $code)) {
            return ['type' => 'pallet_delivery', 'pallet_delivery' => [
                'id'          => $palletDelivery->id,
                'reference'   => $palletDelivery->reference,
                'state'       => $palletDelivery->state->value,
                'state_label' => PalletDeliveryStateEnum::labels()[$palletDelivery->state->value],
            ]];
        }

        return null;
    }

    private function scannedPallet(Warehouse $warehouse, string $code): ?Pallet
    {
        $matching = fn (string $column): Builder => Pallet::where('warehouse_id', $warehouse->id)->whereRaw("lower($column) = lower(?)", [$code]);

        /** @var Pallet|null $pallet */
        $pallet = $matching('reference')->orderByDesc('id')->first() ?? $matching('slug')->first();
        if ($pallet) {
            return $pallet;
        }

        $byCustomerReference = $matching('customer_reference')->limit(2)->get();

        return $byCustomerReference->count() === 1 ? $byCustomerReference->first() : null;
    }

    private function scannedPalletDelivery(Warehouse $warehouse, string $code): ?PalletDelivery
    {
        $matching = fn (string $column, string $value): ?PalletDelivery => PalletDelivery::where('warehouse_id', $warehouse->id)
            ->whereRaw("lower($column) = lower(?)", [$value])
            ->orderByDesc('id')
            ->first();

        $palletDelivery = $matching('reference', $code);
        if (!$palletDelivery && str_starts_with(strtolower($code), self::DELIVERY_BARCODE_PREFIX)) {
            $palletDelivery = $matching('slug', substr($code, strlen(self::DELIVERY_BARCODE_PREFIX)));
        }

        return $palletDelivery;
    }
}
