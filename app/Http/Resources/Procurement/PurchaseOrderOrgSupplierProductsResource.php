<?php

/*
 * author Arya Permana - Kirin
 * created on 15-11-2024-10h-07m
 * github: https://github.com/KirinZero0
 * copyright 2024
*/

namespace App\Http\Resources\Procurement;

use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property int $id
 * @property int $purchase_order_id
 * @property int|null $purchase_order_transaction_id
 * @property int|null $historic_id
 * @property int|null $org_stock_id
 * @property int|null $stock_id
 * @property string $code
 * @property string $name
 * @property string|null $slug
 * @property string|null $supplier_name
 * @property string|null $supplier_slug
 * @property float|null $unit_cost
 * @property float|null $units_per_pack
 * @property float|null $units_per_carton
 * @property string|null $net_currency
 * @property string|null $org_currency
 * @property float|null $org_exchange
 * @property float|null $po_org_exchange
 * @property float|null $quantity_ordered
 * @property float|null $net_amount
 * @property float|null $org_net_amount
 */
class PurchaseOrderOrgSupplierProductsResource extends JsonResource
{
    public function toArray($request): array
    {
        $saveRoute = $this->purchase_order_transaction_id
            ? [
                'name'       => 'grp.models.purchase-order.transaction.update',
                'parameters' => [
                    'purchaseOrder'            => $this->purchase_order_id,
                    'purchaseOrderTransaction' => $this->purchase_order_transaction_id,
                ],
                'method'     => 'patch',
            ]
            : [
                'name'       => 'grp.models.purchase-order.transaction.store',
                'parameters' => [
                    'purchaseOrder'           => $this->purchase_order_id,
                    'historicSupplierProduct' => $this->historic_id,
                    'orgStock'                => $this->org_stock_id,
                ],
                'method'     => 'post',
            ];

        return [
            'id'               => $this->id,
            'slug'             => $this->slug,
            'code'             => $this->code,
            'name'             => $this->name,
            'supplier_name'    => $this->supplier_name,
            'supplier_slug'    => $this->supplier_slug,
            'org_stock_id'     => $this->org_stock_id,
            'image_thumbnail'  => $this->image_sources,

            'unit_cost'        => $this->unit_cost,
            'units_per_pack'   => $this->units_per_pack,
            'units_per_carton' => $this->units_per_carton,
            'quantity_ordered' => $this->quantity_ordered ?? 0,

            'net_amount'       => $this->net_amount,
            'net_currency'     => $this->net_currency,
            'org_net_amount'   => $this->org_net_amount,
            'org_currency'     => $this->org_currency,
            'org_exchange'     => $this->org_exchange ?? $this->po_org_exchange,

            'weight'           => null,
            'volume'           => null,

            'purchase_order_transaction_id' => $this->purchase_order_transaction_id,
            'purchase_order_id'             => $this->purchase_order_id,

            'saveRoute'        => $saveRoute,
            'deleteRoute'      => $this->purchase_order_transaction_id ? [
                'name'       => 'grp.models.purchase-order.transaction.delete',
                'parameters' => [
                    'purchaseOrder'            => $this->purchase_order_id,
                    'purchaseOrderTransaction' => $this->purchase_order_transaction_id,
                ],
                'method'     => 'delete',
            ] : null,
        ];
    }
}
