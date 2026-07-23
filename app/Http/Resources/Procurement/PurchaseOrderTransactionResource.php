<?php

/*
 * Author: Artha <artha@aw-advantage.com>
 * Created: Mon, 17 Apr 2023 11:30:19 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Http\Resources\Procurement;

use App\Models\Procurement\PurchaseOrderTransaction;
use Illuminate\Http\Resources\Json\JsonResource;

class PurchaseOrderTransactionResource extends JsonResource
{
    public function toArray($request): array
    {
        /** @var PurchaseOrderTransaction $transaction */
        $transaction = $this->resource;

        $supplierProduct = $transaction->supplierProduct;
        $tradeUnit       = $transaction->orgStock?->tradeUnits->first(fn ($tradeUnit) => $tradeUnit->image_id !== null);

        return [
            'id'               => $transaction->id,
            'slug'             => $supplierProduct?->slug,
            'code'             => $supplierProduct?->code,
            'name'             => $supplierProduct?->name,
            'supplier_name'    => $supplierProduct?->supplier?->name,
            'supplier_slug'    => $transaction->orgSupplierProduct?->orgSupplier?->slug,
            'org_stock_id'     => $transaction->org_stock_id,
            'image_thumbnail'  => $tradeUnit?->imageSources(64, 64),

            'unit_cost'        => $supplierProduct?->cost,
            'units_per_pack'   => $supplierProduct?->units_per_pack,
            'units_per_carton' => $supplierProduct?->units_per_carton,
            'quantity_ordered' => $transaction->quantity_ordered,

            'net_amount'       => $transaction->net_amount,
            'net_currency'     => $supplierProduct?->currency?->code,
            'org_net_amount'   => $transaction->org_net_amount,
            'org_currency'     => $transaction->organisation?->currency?->code,
            'org_exchange'     => $transaction->org_exchange,

            'weight'           => $transaction->weight === null ? null : (float) $transaction->weight,
            'volume'           => $transaction->volume === null ? null : (float) $transaction->volume,

            'state'            => $transaction->state->value,
            'state_label'      => $transaction->state->labels()[$transaction->state->value],
            'state_icon'       => $transaction->state->stateIcon()[$transaction->state->value],

            'updateRoute'      => [
                'name'       => 'grp.models.purchase-order.transaction.update',
                'parameters' => [
                    'purchaseOrder'            => $transaction->purchase_order_id,
                    'purchaseOrderTransaction' => $transaction->id,
                ],
                'method'     => 'patch',
            ],
            'deleteRoute'      => [
                'name'       => 'grp.models.purchase-order.transaction.delete',
                'parameters' => [
                    'purchaseOrder'            => $transaction->purchase_order_id,
                    'purchaseOrderTransaction' => $transaction->id,
                ],
                'method'     => 'delete',
            ],
        ];
    }
}
