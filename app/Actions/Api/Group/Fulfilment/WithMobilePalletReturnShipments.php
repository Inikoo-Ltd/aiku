<?php

/*
 * Author Louis Perez
 * Created on 17-11-2026-10h-05m
 * GitHub: https://github.com/louis-perez
 * Copyright 2026
*/

namespace App\Actions\Api\Group\Fulfilment;

use App\Actions\Api\Group\GoodsOut\GetMobileDeliveryNote;
use App\Enums\Fulfilment\PalletReturn\PalletReturnStateEnum;
use App\Http\Resources\Dispatching\ShipmentsResource;
use App\Models\Dispatching\Shipment;
use App\Models\Fulfilment\PalletReturn;
use App\Models\Inventory\Warehouse;
use App\Models\SysAdmin\User;
use Illuminate\Support\Arr;

trait WithMobilePalletReturnShipments
{
    protected function ensureShipmentOfReturn(PalletReturn $palletReturn, Shipment $shipment): void
    {
        abort_unless($palletReturn->shipments()->whereKey($shipment->id)->exists(), 404);
    }

    protected function ensureShipmentCanBeAdded(PalletReturn $palletReturn): void
    {
        if (!in_array($palletReturn->state, [PalletReturnStateEnum::PICKING, PalletReturnStateEnum::PICKED], true)) {
            $this->refuseState(__('Shipments can only be added while the return is picking or picked.'));
        }

        if ($palletReturn->is_collection) {
            $this->refuseState(__('The customer collects this return, so it needs no shipment.'));
        }
    }

    protected function shipmentsAreLocked(PalletReturn $palletReturn): bool
    {
        return in_array($palletReturn->state, [PalletReturnStateEnum::DISPATCHED, PalletReturnStateEnum::CANCEL], true);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    protected function shipmentRows(Warehouse $warehouse, PalletReturn $palletReturn, User $user): array
    {
        $canDelete = self::canEditFulfilmentWarehouse($user, $warehouse) && !$this->shipmentsAreLocked($palletReturn);
        $shipments = $palletReturn->shipments()->with('shipper')->orderBy('shipments.id')->get();
        $rows      = ShipmentsResource::collection($shipments)->resolve();

        return $shipments
            ->values()
            ->map(fn (Shipment $shipment, int $position) => [
                'id'            => $shipment->id,
                'shipper'       => $rows[$position]['name'],
                'shipper_code'  => $rows[$position]['shipper_code'],
                'tracking'      => $rows[$position]['tracking'],
                'trackings'     => array_values((array) $rows[$position]['trackings']),
                'tracking_urls' => array_values((array) $rows[$position]['tracking_urls']),
                'is_printable'  => (bool) $rows[$position]['is_printable'],
                'label'         => $this->returnShipmentLabel($warehouse, $palletReturn, $shipment),
                'can_delete'    => $canDelete,
            ])
            ->all();
    }

    /**
     * @return array{kind: string, url: string|null}
     */
    protected function returnShipmentLabel(Warehouse $warehouse, PalletReturn $palletReturn, Shipment $shipment): array
    {
        if (GetMobileDeliveryNote::hasPdfLabel($shipment)) {
            return [
                'kind' => 'download',
                'url'  => route('grp.api.mobile.warehouses.pallet_returns.shipments.label', [
                    'warehouse'    => $warehouse->id,
                    'palletReturn' => $palletReturn->id,
                    'shipment'     => $shipment->id,
                ]),
            ];
        }

        if ($shipment->combined_label_url) {
            return ['kind' => 'external', 'url' => $shipment->combined_label_url];
        }

        return ['kind' => 'none', 'url' => null];
    }

    /**
     * @return array<int, array{weight: float, dimensions: array<int, float>}>
     */
    protected function parcelRows(PalletReturn $palletReturn): array
    {
        return collect((array) $palletReturn->parcels)
            ->filter(fn ($parcel) => is_array($parcel))
            ->map(fn (array $parcel) => [
                'weight'     => (float) Arr::get($parcel, 'weight'),
                'dimensions' => array_map(fn ($dimension) => (float) $dimension, array_values((array) Arr::get($parcel, 'dimensions', []))),
            ])
            ->values()
            ->all();
    }
}
