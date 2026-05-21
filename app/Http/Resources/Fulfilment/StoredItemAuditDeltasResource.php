<?php

/*
 * author Arya Permana - Kirin
 * created on 16-01-2025-14h-07m
 * github: https://github.com/KirinZero0
 * copyright 2025
*/

namespace App\Http\Resources\Fulfilment;

use App\Enums\Fulfilment\PalletReturn\PalletReturnTypeEnum;
use App\Models\Fulfilment\PalletDelivery;
use App\Models\Fulfilment\PalletReturn;
use App\Models\Fulfilment\StoredItemAudit;
use Illuminate\Http\Resources\Json\JsonResource;

class StoredItemAuditDeltasResource extends JsonResource
{
    public function toArray($request): array
    {

        $desc_model = '';
        $desc_title = '';
        $desc_after_title = '';
        $route = null;
        $icon = null;
        $retina = str_starts_with($request->route()->getName(), 'retina.');

        
        if ($this->stored_item_audit_id) {
            $desc_title = $this->stored_item_audit_reference;
            $desc_model = __('Stored Item Audit');
            if ($retina) {
                $route = [
                    'name' => 'retina.fulfilment.storage.stored-items-audits.show',
                    'parameters' => [
                        'storedItemAudit' => $this->stored_item_audit_slug
                    ]
                ];
            } else {
                $route = [
                    'name' => 'grp.helpers.redirect_stored_item_audit',
                    'parameters' => [
                        'storedItemAudit' => $this->stored_item_audit_id
                    ]
                ];
            }
            $icon = 'fal fa-narwhal';
        } elseif ($this->pallet_delivery_id) {
            $desc_title = $this->pallet_delivery_reference;
            $desc_model = __('Pallet Delivery');
            if ($retina) {
                $route = [
                    'name' => 'retina.fulfilment.storage.pallet_deliveries.show',
                    'parameters' => [
                        'palletDelivery' => $this->pallet_delivery_slug
                    ]
                ];
            } else {
                $route = [
                    'name' => 'grp.helpers.redirect_pallet_delivery',
                    'parameters' => [
                        'palletDelivery' => $this->pallet_delivery_id
                    ]
                ];
            }
            $icon = 'fal fa-truck-couch';
        } elseif ($this->pallet_returns_id) {
            $desc_title = $this->pallet_returns_reference;
            $desc_model = __('Pallet Return');
            if ($retina) {
                if ($this->pallet_returns_type == PalletReturnTypeEnum::PALLET->value) {
                    $route = [
                        'name' => '	retina.fulfilment.storage.pallet_returns.show',
                        'parameters' => [
                            'palletReturn' => $this->pallet_returns_slug
                        ]
                    ];
                } else {
                    $route = [
                        'name' => 'retina.fulfilment.storage.pallet_returns.with-stored-items.show',
                        'parameters' => [
                            'palletReturn' => $this->pallet_returns_slug
                        ]
                    ];
                }
            } else {
                $route = [
                    'name' => 'grp.helpers.redirect_pallet_return',
                    'parameters' => [
                        'palletReturn' => $this->pallet_returns_id
                    ]
                ];
            }
            $icon = 'fal fa-sign-out-alt';
        } else {
            $desc_title = '-';
            $desc_model = __('Initial Setup');
            $route = null;
        }

        return [
            'id'                                => $this->id,
            'pallet_id'                         => $this->pallet_id,
            'pallet_customer_reference'         => $this->pallet_customer_reference,
            'stored_item_id'                    => $this->stored_item_id,
            'stored_item_reference'             => $this->stored_item_reference,
            'audited_at'                        => $this->audited_at,
            'original_quantity'                 => (int) $this->original_quantity,
            'audited_quantity'                  => (int) $this->audited_quantity,
            'audit_type'                        => $this->audit_type,
            'description' => [
                'model' => $desc_model,
                'title' => $desc_title,
                'route' => $route,
                'after_title' => $desc_after_title,
                'icon' => $icon
            ],
            'audit_type_label'                  => $this->audit_type->labels()[$this->audit_type->value],
            'state'                             => $this->state,
            'state_label'                       => $this->state->labels()[$this->state->value],
            'state_icon'                        => $this->state->stateIcon()[$this->state->value]
        ];
    }
}
