<?php

/*
 * author Louis Perez
 * created on 11-05-2026-09h-44m
 * github: https://github.com/louis-perez
 * copyright 2026
*/

namespace App\Http\Resources\Procurement;

use Illuminate\Http\Resources\Json\JsonResource;

class SowingResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id'                         => $this->id,
            'quantity'            => (float)$this->quantity,
            'quantity_fractional' => riseDivisor(
                divideWithRemainder(findSmallestFactors($this->quantity)),
                $this->orgStock?->packed_in
            ),
            'sower_name'                 => $this->sower->contact_name,
            'type'                       => $this->type,
            'location_code'              => $this->location?->code,
            'location_slug'              => $this->location?->slug,
            'location_id'                => $this->location?->id,
            'org_stock_id'               => $this->org_stock_id,
            'organisation_id'            => $this->organisation_id,
            // 'update_route'       => [
            //     'name'       => 'grp.models.picking.update',
            //     'parameters' => [
            //         'picking' => $this->id
            //     ],
            //     'method'     => 'patch'
            // ],
            'undo_sowing_route' => [
                'name'       => 'grp.models.sowing.delete',
                'parameters' => [
                    'sowing' => $this->id
                ],
                'method'     => 'delete'
            ],
        ];
    }
}
