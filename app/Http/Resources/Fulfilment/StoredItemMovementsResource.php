<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 03 Feb 2024 11:07:20 Malaysia Time, Bali Airport, Indonesia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Http\Resources\Fulfilment;

use App\Enums\Fulfilment\PalletReturn\PalletReturnTypeEnum;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property mixed $id
 * @property mixed $reference
 * @property mixed $customer_reference
 * @property mixed $fulfilment_customer_id
 * @property mixed $slug
 * @property mixed $notes
 * @property mixed $state
 * @property mixed $type
 * @property mixed $storedItems
 * @property mixed $rental_id
 * @property mixed $status
 * @property mixed $location_slug
 * @property mixed $location_code
 * @property mixed $location_id
 * @property mixed $warehouse_id
 * @property mixed $pallet_delivery_id
 * @property mixed $pallet_return_id
 * @property mixed $fulfilment_customer_name
 * @property mixed $fulfilment_customer_slug
 * @property mixed $stored_item_audit_id
 */
class StoredItemMovementsResource extends JsonResource
{
    public function toArray($request): array
    {

        $desc_after_title = '';

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
                    'name' => '	grp.helpers.redirect_pallet_delivery',
                    'parameters' => [
                        'palletDelivery' => $this->pallet_delivery_id
                    ]
                ];
            }
            $icon = 'fal fa-truck-couch';
        } elseif ($this->pallet_returns_reference) {
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
            'id'                    => $this->id,
            'slug'                  => $this->slug,
            'reference'             => $this->reference,
            'delta'                 => $this->delta,
            'pallet_reference'      => $this->pallet_reference,
            'stored_item_reference' => $this->stored_item_reference,
            'pallet_slug'           => $this->pallet_slug,
            'type'                  => $this->type,
            'location_slug'         => $this->location_slug,
            'location_code'         => $this->location_code,
            'description'           => [
                'model'         => $desc_model,
                'title'         => $desc_title,
                'route'         => $route,
                'after_title'   => $desc_after_title,
                'icon'          => $icon
            ],
            'moved_at'              => $this->moved_at->format('Y-m-d H:i:s')
        ];
    }
}
