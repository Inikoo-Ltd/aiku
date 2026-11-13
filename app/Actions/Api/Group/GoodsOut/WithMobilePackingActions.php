<?php

/*
 * Author Louis Perez
 * Created on 13-11-2026-14h-39m
 * GitHub: https://github.com/louis-perez
 * Copyright 2026
*/

namespace App\Actions\Api\Group\GoodsOut;

use App\Actions\Dispatching\DeliveryNote\UI\ShowDeliveryNote;
use App\Enums\Catalogue\Shop\ShopEngineEnum;
use App\Enums\Dispatching\DeliveryNote\DeliveryNoteStateEnum;
use App\Enums\Ordering\Platform\PlatformTypeEnum;
use App\Models\Dispatching\DeliveryNote;
use Illuminate\Support\Arr;

trait WithMobilePackingActions
{
    private const array BLOCKABLE_ACTION_ROUTES = [
        'start_packing'         => 'grp.models.delivery_note.state.packing',
        'set_as_packed'         => 'grp.models.delivery_note.state.packed',
        'dispatch'              => 'grp.models.delivery_note.state.dispatched',
        'finalise_and_dispatch' => 'grp.models.delivery_note.state.finalise_and_dispatch',
        'finalise'              => 'grp.models.delivery_note.state.finalised',
    ];

    private const array PACKED_ACTION_KEYS = [
        'grp.models.delivery_note.state.finalise_and_dispatch' => 'finalise_and_dispatch',
        'grp.models.delivery_note.state.finalised'             => 'finalise',
        'grp.models.delivery_note.state.dispatched'            => 'dispatch',
    ];

    private const string FAIRE_SHIPMENT_ROUTE = 'grp.models.delivery_note.shipment.store_faire';

    private const string TIKTOK_SHIPMENT_ROUTE = 'grp.models.delivery_note.shipment.store_tiktok';

    /**
     * @return array<string, string>
     */
    private function packingActions(DeliveryNote $deliveryNote, bool $canHandle): array
    {
        $actions = [];
        if (!$canHandle) {
            $actions['take_over'] = __('Unlock to pack');
        } elseif (!$this->isDropshipping($deliveryNote)) {
            $actions['set_as_packed'] = __('Set as packed');
        }

        $actions['undo_packing'] = __('Undo packing');

        return $actions;
    }

    /**
     * @return array<string, string>
     */
    private function packedActions(DeliveryNote $deliveryNote, bool $isEditable, bool $canShip): array
    {
        $actions      = [];
        $packedAction = ShowDeliveryNote::make()->getPackedActions($deliveryNote);
        $key          = self::PACKED_ACTION_KEYS[Arr::get($packedAction, 'route.name')] ?? null;

        if ($key && ($key === 'dispatch' ? $canShip : $isEditable)) {
            $actions[$key] = $packedAction['label'];
        }

        $actions += $this->shipmentActions($deliveryNote, $canShip);

        if ($isEditable) {
            $actions['unpack'] = __('Unpack');
        }

        return $actions;
    }

    /**
     * @return array<string, string>
     */
    private function finalisedActions(DeliveryNote $deliveryNote, bool $isEditable, bool $canShip): array
    {
        $actions = [];
        if ($canShip) {
            $actions['dispatch'] = $deliveryNote->collection_address_id ? __('Set as collected') : __('Dispatch');
        }

        $actions += $this->shipmentActions($deliveryNote, $canShip);

        if ($isEditable) {
            $actions['unpack'] = __('Unpack');
        }

        return $actions;
    }

    /**
     * @return array<string, string>
     */
    private function shipmentActions(DeliveryNote $deliveryNote, bool $canShip): array
    {
        if (!$canShip) {
            return [];
        }

        if (!$deliveryNote->is_shipping_by_external) {
            return $deliveryNote->collection_address_id ? [] : ['add_shipment' => __('Add shipment')];
        }

        $externalShipmentRoute = $this->externalShipmentRoute($deliveryNote);

        if ($externalShipmentRoute && $deliveryNote->state === DeliveryNoteStateEnum::PACKED && $deliveryNote->shipments()->doesntExist()) {
            return ['get_external_shipment' => $externalShipmentRoute['label']];
        }

        return [];
    }

    /**
     * @param array<string, string> $actions
     * @return array<int, array{key: string, label: string, disabled_reason?: string}>
     */
    private function listedActions(DeliveryNote $deliveryNote, array $actions): array
    {
        $blockedActions = array_intersect_key($actions, self::BLOCKABLE_ACTION_ROUTES)
            ? ShowDeliveryNote::getBlockedActions($deliveryNote)
            : null;

        return collect($actions)
            ->map(function (string $label, string $key) use ($blockedActions) {
                $action = ['key' => $key, 'label' => $label];

                if ($blockedActions && in_array(self::BLOCKABLE_ACTION_ROUTES[$key] ?? null, $blockedActions['routes'], true)) {
                    $action['disabled_reason'] = $blockedActions['reason'];
                }

                return $action;
            })
            ->values()
            ->all();
    }

    /**
     * @return array{label: string, name: string, parameters: array{deliveryNote: int}}|null
     */
    protected function externalShipmentRoute(DeliveryNote $deliveryNote): ?array
    {
        return ShowDeliveryNote::make()->getExternalShipmentRoute($deliveryNote);
    }

    protected function isFaireShipment(DeliveryNote $deliveryNote): bool
    {
        return ($this->externalShipmentRoute($deliveryNote)['name'] ?? null) === self::FAIRE_SHIPMENT_ROUTE;
    }

    protected function externalEngineLabel(DeliveryNote $deliveryNote): ?string
    {
        if (!$deliveryNote->is_shipping_by_external) {
            return null;
        }

        return match ($this->externalShipmentRoute($deliveryNote)['name'] ?? null) {
            self::FAIRE_SHIPMENT_ROUTE  => ShopEngineEnum::FAIRE->label(),
            self::TIKTOK_SHIPMENT_ROUTE => PlatformTypeEnum::TIKTOK->labels()[PlatformTypeEnum::TIKTOK->value],
            default                     => $deliveryNote->platform?->name ?? $deliveryNote->shop->engine?->label(),
        };
    }
}
