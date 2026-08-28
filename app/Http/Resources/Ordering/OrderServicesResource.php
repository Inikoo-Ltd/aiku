<?php

namespace App\Http\Resources\Ordering;

use Illuminate\Http\Resources\Json\JsonResource;

class OrderServicesResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id'                 => $this->id,
            'slug'               => $this->slug,
            'historic_id'        => $this->historic_asset_id,
            'code'               => $this->code,
            'name'               => $this->name,
            'unit'               => $this->unit,
            'quantity_ordered'   => $this->quantity_ordered ?? 0,
            'transaction_id'     => $this->transaction_id ?? null,
            'order_id'           => $this->order_id ?? null,
            'price'              => $this->price,
            'currency_code'      => $this->currency_code,
            'description'        => $this->description,
            'state'              => $this->state,
            'deleteRoute'        => $this->transaction_id ? [
                'name'       => 'grp.models.transaction.delete',
                'parameters' => [
                    'transaction' => $this->transaction_id
                ],
                'method'     => 'delete'
            ] : null,
            'updateRoute'        => $this->transaction_id ? [
                'name'       => 'grp.models.transaction.update',
                'parameters' => [
                    'transaction' => $this->transaction_id
                ],
                'method'     => 'patch'
            ] : null,
        ];
    }
}
