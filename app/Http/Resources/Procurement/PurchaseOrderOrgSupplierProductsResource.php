<?php

/*
 * author Arya Permana - Kirin
 * created on 15-11-2024-10h-07m
 * github: https://github.com/KirinZero0
 * copyright 2024
*/

namespace App\Http\Resources\Procurement;

use App\Models\Goods\Stock;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property string $code
 * @property string $name
 * @property string $slug
 * @property string $created_at
 * @property string $updated_at
 * @property string $description
 */
class PurchaseOrderOrgSupplierProductsResource extends JsonResource
{
    public function toArray($request): array
    {

        $stock = Stock::find($this->stock_id);

        $imageThumbnail = $stock ? $stock->imageSources(40, 40) : null;

        /** @var Stock $stock */
        return [
            'id'              => $this->id,
            'historic_id'     => $this->historic_id,
            'org_stock_id'    => $this->org_stock_id,
            'code'            => $this->code,
            'name'            => $this->name,
            'supplier_name'   => $this->supplier_name,
            // 'image_thumbnail' => $stock->imageSources(40, 40),
            'image_thumbnail' => $imageThumbnail,
            'quantity_ordered' => $this->quantity_ordered ?? 0,
            'purchase_order_transaction_id' => $this->purchase_order_transaction_id,
            'purchase_order_id'  => $this->purchase_order_id,
            'updateRoute'       => [
                'name' => 'grp.models.purchase-order.transaction.update',
                'parameters' => [
                    'purchaseOrder' => $this->purchase_order_id,
                    'purchaseOrderTransaction' => $this->purchase_order_transaction_id
                ]
            ],
            'deleteRoute'       => [
                'name' => 'grp.models.purchase-order.transaction.delete',
                'parameters' => [
                    'purchaseOrder' => $this->purchase_order_id,
                    'purchaseOrderTransaction' => $this->purchase_order_transaction_id
                ]
            ]

        ];
    }
}
