<?php

/*
 * author Arya Permana - Kirin
 * created on 23-12-2024-15h-21m
 * github: https://github.com/KirinZero0
 * copyright 2024
*/

namespace App\Http\Resources\CRM;

use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property mixed $number_customers
 * @property mixed $total_customers
 */
class TrafficSourcesResource extends JsonResource
{
    public function toArray($request): array
    {
        /** @var \App\Models\CRM\TrafficSource $trafficSource */
        $trafficSource = $this->resource;


        return [
            'id'                => $trafficSource->id,
            'slug'              => $trafficSource->slug,
            'name'              => $trafficSource->name,
            'number_customers'  => $trafficSource->number_customers ?? 0,
            'number_customer_purchases' => $trafficSource->number_customer_purchases ?? 0,
            'total_customer_revenue' => $trafficSource->total_customer_revenue ?? 0,
            'created_at'        => $trafficSource->created_at,
            'updated_at'        => $trafficSource->updated_at,
        ];
    }
}
