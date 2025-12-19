<?php

/*
 * Author: Oggie Sutrisna
 * Created: Thu, 18 Dec 2025 13:50:00 Makassar Time
 * Description: Resource for single OrderReturn model with full details
 */

namespace App\Http\Resources\Dispatching;

use App\Enums\GoodsIn\Return\ReturnStateEnum;
use App\Http\Resources\CRM\CustomerResource;
use App\Http\Resources\Helpers\AddressResource;
use Illuminate\Http\Resources\Json\JsonResource;

class OrderReturnResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id'                => $this->id,
            'slug'              => $this->slug,
            'reference'         => $this->reference,
            'state'             => $this->state,
            'state_icon'        => ReturnStateEnum::stateIcon()[$this->state->value] ?? null,
            'state_label'       => $this->state->labels()[$this->state->value] ?? null,
            'date'              => $this->date,
            'received_at'       => $this->received_at,
            'inspecting_at'     => $this->inspecting_at,
            'processed_at'      => $this->processed_at,
            'cancelled_at'      => $this->cancelled_at,
            'number_items'      => $this->number_items,
            'weight'            => $this->weight,
            'estimated_weight'  => $this->estimated_weight,
            'customer_notes'    => $this->customer_notes,
            'internal_notes'    => $this->internal_notes,
            'return_reason'     => $this->return_reason,
            'email'             => $this->email,
            'phone'             => $this->phone,
            'customer'          => $this->whenLoaded('customer', function () {
                return CustomerResource::make($this->customer);
            }),
            'address'           => $this->whenLoaded('address', function () {
                return AddressResource::make($this->address);
            }),
            'warehouse'         => [
                'id'   => $this->warehouse_id,
                'slug' => $this->warehouse?->slug,
                'name' => $this->warehouse?->name,
            ],
            'shop'              => [
                'id'   => $this->shop_id,
                'slug' => $this->shop?->slug,
                'name' => $this->shop?->name,
            ],
            'stats'             => $this->whenLoaded('stats', function () {
                return [
                    'number_items'              => $this->stats->number_items,
                    'number_items_pending'      => $this->stats->number_items_state_pending,
                    'number_items_received'     => $this->stats->number_items_state_received,
                    'number_items_inspecting'   => $this->stats->number_items_state_inspecting,
                    'number_items_accepted'     => $this->stats->number_items_state_accepted,
                    'number_items_rejected'     => $this->stats->number_items_state_rejected,
                    'number_items_restocked'    => $this->stats->number_items_state_restocked,
                    'total_quantity_expected'   => $this->stats->total_quantity_expected,
                    'total_quantity_received'   => $this->stats->total_quantity_received,
                    'total_quantity_accepted'   => $this->stats->total_quantity_accepted,
                    'total_quantity_rejected'   => $this->stats->total_quantity_rejected,
                    'total_refund_amount'       => $this->stats->total_refund_amount,
                ];
            }),
            'orders'            => $this->whenLoaded('orders', function () {
                return $this->orders->map(fn ($order) => [
                    'id'        => $order->id,
                    'slug'      => $order->slug,
                    'reference' => $order->reference,
                ]);
            }),
            'created_at'        => $this->created_at,
            'updated_at'        => $this->updated_at,
        ];
    }
}
