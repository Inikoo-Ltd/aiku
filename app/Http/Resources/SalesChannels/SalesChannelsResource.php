<?php

namespace App\Http\Resources\SalesChannels;

use Illuminate\Http\Resources\Json\JsonResource;

class SalesChannelsResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array
     */
    public function toArray($request)
    {
        return [
            'id'                => $this->id,
            'name'              => $this->name,
            'code'              => $this->code,
            'type'              => $this->type,
            'is_active'         => $this->is_active,
            // 'show_in_dashboard' => $this->show_in_dashboard,
            'refunds'           => $this->refunds,
            'invoices'          => $this->invoices,
            'sales'             => $this->sales,
        ];
    }
}
