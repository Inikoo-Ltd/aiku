<?php

/*
 * Author: Ganes <gustiganes@gmail.com>
 * Created on: 02-01-2025, Bali, Indonesia
 * Github: https://github.com/Ganes556
 * Copyright: 2025
 *
*/

namespace App\Http\Resources\Inventory;

use Illuminate\Http\Resources\Json\JsonResource;
use App\Models\Inventory\OrgStockMovement;

/**
 * @property mixed $id
 * @property mixed $date
 * @property mixed $class
 * @property mixed $type
 * @property mixed $flow
 * @property mixed $quantity
 * @property mixed $org_stock_name
 * @property mixed $org_stock_slug
 * @property mixed $org_amount
 * @property mixed $organisation_name
 * @property mixed $organisation_slug
 * @property mixed $warehouse_name
 * @property mixed $warehouse_slug
 * @property mixed $location_code
 * @property mixed $location_slug
 * @property mixed $operation_type
 * @property mixed $operation_id
 * @property mixed $currency_code
 */
class OrgStockMovementsResource extends JsonResource
{
    public function toArray($request): array
    {
        /** @var OrgStockMovement $orgStockMovement */
        $orgStockMovement = $this->resource;

        return [
            'id'                => $orgStockMovement->id,
            'date'              => $orgStockMovement->date,
            'class'             => $orgStockMovement->class,
            'type'              => $orgStockMovement->type,
            'flow'              => $orgStockMovement->flow,
            'quantity'          => $orgStockMovement->quantity,
            'org_stock_name'    => $orgStockMovement->org_stock_name,
            'org_stock_slug'    => $orgStockMovement->org_stock_slug,
            'org_amount'        => $orgStockMovement->org_amount,
            'organisation_name' => $orgStockMovement->organisation_name,
            'organisation_slug' => $orgStockMovement->organisation_slug,
            'warehouse_name'    => $orgStockMovement->warehouse_name,
            'warehouse_slug'    => $orgStockMovement->warehouse_slug,
            'location_code'     => $orgStockMovement->location_code,
            'location_slug'     => $orgStockMovement->location_slug,
            'operation_type'    => $orgStockMovement->operation_type,
            'operation_id'      => $orgStockMovement->operation_id,
            'currency_code'     => $orgStockMovement->currency_code,
            'user'              => $orgStockMovement->user,
        ];
    }
}
