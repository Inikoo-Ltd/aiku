<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 22 Feb 2023 22:40:34 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Http\Resources\Dispatching;

use App\Enums\Dispatching\Picking\PickingTypeEnum;
use App\Enums\Inventory\LocationStock\LocationStockTypeEnum;
use App\Http\Resources\Inventory\LocationOrgStocksResource;
use App\Models\Dispatching\DeliveryNoteItem;
use App\Models\Inventory\OrgStock;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property mixed $org_stock_id
 * @property mixed $id
 * @property mixed $state
 * @property mixed $quantity_required
 * @property mixed $quantity_picked
 * @property mixed $org_stock_code
 * @property mixed $org_stock_name
 * @property mixed $is_handled
 * @property mixed $quantity_packed
 * @property mixed $quantity_not_picked
 * @property mixed $quantity_dispatched
 */
class DeliveryNoteItemsResource extends JsonResource
{
    public function toArray($request): array
    {
        $requiredFactionalData = divideWithRemainder(findSmallestFactors($this->quantity_required));


        $orgStock         = OrgStock::find($this->org_stock_id);
        $deliveryNoteItem = DeliveryNoteItem::find($this->id);
        $fullWarning      = [
            'disabled' => false,
            'message'  => ''
        ];
        if ($deliveryNoteItem->quantity_picked == $deliveryNoteItem->quantity_required) {
            $fullWarning = [
                'disabled' => true,
                'message'  => __('The required quantity has already been fully picked.')
            ];
        }
        $pickingLocations = $orgStock->locationOrgstocks->where('type', LocationStockTypeEnum::PICKING);

        return [
            'id'                           => $this->id,
            'state'                        => $this->state,
            'state_icon'                   => $this->state->stateIcon()[$this->state->value],
            'quantity_required'            => $this->quantity_required,
            'quantity_to_pick'             => max(0, $this->quantity_required - $this->quantity_picked),
            'quantity_picked'              => $this->quantity_picked,
            'quantity_picked_fractional'   => divideWithRemainder(findSmallestFactors($this->quantity_picked)),
            'quantity_not_picked'          => $this->quantity_not_picked,
            'quantity_packed'              => $this->quantity_packed,
            'quantity_dispatched'          => $this->quantity_dispatched,
            'org_stock_code'               => $this->org_stock_code,
            'org_stock_name'               => $this->org_stock_name,
            'locations'                    => $pickingLocations->isNotEmpty() ? LocationOrgStocksResource::collection($pickingLocations) : [],
            'pickings'                     => $deliveryNoteItem->pickings->where('type', PickingTypeEnum::PICK)
                ? $deliveryNoteItem->pickings->where('type', PickingTypeEnum::PICK)
                    ->keyBy(function ($item) {
                        return $item->location_id
                            ?? ($item->location->id ?? 'not-picked');
                    })
                    ->map(fn ($item) => new PickingsResource($item))
                : [],
            'packings'                     => $deliveryNoteItem->packings ? PackingsResource::collection($deliveryNoteItem->packings) : [],
            'warning'                      => $fullWarning,
            'is_handled'                   => $this->is_handled,
            'is_packed'                    => $this->quantity_packed == $this->quantity_picked,
            'quantity_required_fractional' => $requiredFactionalData,
            'picking_route'                => [
                'name'       => 'grp.models.delivery_note_item.picking.store',
                'parameters' => [
                    'deliveryNoteItem' => $this->id
                ],
                'method'     => 'post'
            ],
            'picking_all_route'            => [
                'name'       => 'grp.models.delivery_note_item.picking_all.store',
                'parameters' => [
                    'deliveryNoteItem' => $this->id
                ],
                'method'     => 'post'
            ],
            'not_picking_route'            => [
                'name'       => 'grp.models.delivery_note_item.not_picking.store',
                'parameters' => [
                    'deliveryNoteItem' => $this->id
                ],
                'method'     => 'post'
            ],
            'packing_route'                => [
                'name'       => 'grp.models.delivery_note_item.packing.store',
                'parameters' => [
                    'deliveryNoteItem' => $this->id
                ],
                'method'     => 'post'
            ],
            'pickers_list_route'           => [
                'name'       => 'grp.json.employees.picker_users',
                'parameters' => [
                    'organisation' => $deliveryNoteItem->organisation->slug
                ]
            ],
            'packers_list_route'           => [
                'name'       => 'grp.json.employees.packers',
                'parameters' => [
                    'organisation' => $deliveryNoteItem->organisation->slug
                ]
            ],
        ];
    }
}
