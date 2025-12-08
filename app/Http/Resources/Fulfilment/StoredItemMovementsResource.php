<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 03 Feb 2024 11:07:20 Malaysia Time, Bali Airport, Indonesia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Http\Resources\Fulfilment;

use App\Enums\Fulfilment\PalletReturn\PalletReturnTypeEnum;
use App\Models\Fulfilment\PalletDelivery;
use App\Models\Fulfilment\PalletReturn;
use App\Models\Fulfilment\StoredItemAudit;
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
 */
class StoredItemMovementsResource extends JsonResource
{
    public function toArray($request): array
    {
        $desc_model = '';
        $desc_title = '';
        $desc_after_title = '';
        $route = null;
        $icon = null;
        $retina = str_starts_with($request->route()->getName(), 'retina.');
        if ($this->stored_item_audit_reference) {
            $storedItem = StoredItemAudit::where('reference', $this->stored_item_audit_reference)->first();
            if ($storedItem) {
                $desc_title = $storedItem->reference;
                $desc_model = __('Stored Item Audit');
                if ($retina) {
                    $route = [
                        'name' => 'retina.fulfilment.storage.stored-items-audits.show',
                        'parameters' => [
                            'storedItemAudit' => $storedItem->slug,
                        ],
                    ];
                } else {
                    $route = [
                        'name' => 'grp.org.fulfilments.show.crm.customers.show.stored-item-audits.show',
                        'parameters' => [
                            'organisation' => $storedItem->organisation->slug,
                            'fulfilment' => $storedItem->fulfilment->slug,
                            'fulfilmentCustomer' => $storedItem->fulfilmentCustomer->slug,
                            'storedItemAudit' => $storedItem->slug,
                        ],
                    ];
                }

                $icon = 'fal fa-narwhal';
            }
        } elseif ($this->pallet_delivery_reference) {
            $palletDelivery = PalletDelivery::where('reference', $this->pallet_delivery_reference)->first();
            if ($palletDelivery) {
                $desc_title = $palletDelivery->reference;
                $desc_model = __('Pallet Delivery');
                if ($retina) {
                    $route = [
                        'name' => 'retina.fulfilment.storage.pallet_deliveries.show',
                        'parameters' => [
                            'palletDelivery' => $palletDelivery->slug,
                        ],
                    ];
                } else {
                    $route = [
                        'name' => 'grp.org.fulfilments.show.crm.customers.show.pallet_deliveries.show',
                        'parameters' => [
                            'organisation' => $palletDelivery->organisation->slug,
                            'fulfilment' => $palletDelivery->fulfilment->slug,
                            'fulfilmentCustomer' => $palletDelivery->fulfilmentCustomer->slug,
                            'palletDelivery' => $palletDelivery->slug,
                        ],
                    ];
                }
                $icon = 'fal fa-truck-couch';
            }
        } elseif ($this->pallet_returns_reference) {
            $palletReturn = PalletReturn::where('reference', $this->pallet_returns_reference)->first();
            if ($palletReturn) {
                $desc_title = $palletReturn->reference;
                $desc_model = __('Pallet Return');

                if ($retina) {
                    if ($palletReturn->type == PalletReturnTypeEnum::PALLET) {
                        $route = [
                            'name' => '	retina.fulfilment.storage.pallet_returns.show',
                            'parameters' => [
                                'palletReturn' => $palletReturn->slug,
                            ],
                        ];
                    } else {
                        $route = [
                            'name' => 'retina.fulfilment.storage.pallet_returns.with-stored-items.show',
                            'parameters' => [
                                'palletReturn' => $palletReturn->slug,
                            ],
                        ];
                    }
                } else {
                    if ($palletReturn->type == PalletReturnTypeEnum::PALLET) {
                        $route = [
                            'name' => 'grp.org.fulfilments.show.crm.customers.show.pallet_returns.show',
                            'parameters' => [
                                'organisation' => $palletReturn->organisation->slug,
                                'fulfilment' => $palletReturn->fulfilment->slug,
                                'fulfilmentCustomer' => $palletReturn->fulfilmentCustomer->slug,
                                'palletReturn' => $palletReturn->slug,
                            ],
                        ];
                    } else {
                        $route = [
                            'name' => 'grp.org.fulfilments.show.crm.customers.show.pallet_returns.with_stored_items.show',
                            'parameters' => [
                                'organisation' => $palletReturn->organisation->slug,
                                'fulfilment' => $palletReturn->fulfilment->slug,
                                'fulfilmentCustomer' => $palletReturn->fulfilmentCustomer->slug,
                                'palletReturn' => $palletReturn->slug,
                            ],
                        ];
                    }
                }
                $icon = 'fal fa-sign-out-alt';
            }
        } else {
            $desc_title = '-';
            $desc_model = __('Initial Setup');
            $route = null;
        }

        return [
            'id' => $this->id,
            'slug' => $this->slug,
            'reference' => $this->reference,
            'delta' => $this->delta,
            'pallet_reference' => $this->pallet_reference,
            'stored_item_reference' => $this->stored_item_reference,
            'pallet_slug' => $this->pallet_slug,
            'type' => $this->type,
            'location_slug' => $this->location_slug,
            'location_code' => $this->location_code,
            'description' => [
                'model' => $desc_model,
                'title' => $desc_title,
                'route' => $route,
                'after_title' => $desc_after_title,
                'icon' => $icon,
            ],
        ];
    }
}
