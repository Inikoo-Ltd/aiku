<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 23 Sep 2026 Malaga, Spain
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Http\Resources\Procurement;

use App\Actions\Procurement\GetOrganisationStockCoverBuckets;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property int $id
 * @property string $slug
 * @property string $code
 * @property string $name
 * @property ?string $family_code
 * @property ?string $health_rank
 * @property float $quantity_available
 * @property ?float $days_of_cover
 * @property int $lead_time_days
 * @property ?string $supplier_code
 * @property ?int $on_the_way_po_count
 * @property ?float $recommended_order_quantity
 * @property ?float $stock_value
 * @property string $bucket
 */
class StockCoverOrgStocksResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id'                   => $this->id,
            'slug'                 => $this->slug,
            'code'                 => $this->code,
            'name'                 => $this->name,
            'family_code'          => $this->family_code,
            'health_rank'          => $this->health_rank,
            'quantity_available'   => (float) $this->quantity_available,
            'days_of_cover'        => $this->days_of_cover !== null ? (int) $this->days_of_cover : null,
            'lead_time_days'       => (int) $this->lead_time_days,
            'supplier_code'        => $this->supplier_code,
            'on_the_way'           => (int) $this->on_the_way_po_count > 0,
            'recommended_quantity' => $this->recommended_order_quantity !== null ? (int) ceil((float) $this->recommended_order_quantity) : null,
            'stock_value'          => (float) $this->stock_value,
            'bucket'               => $this->bucket,
            'bucket_label'         => GetOrganisationStockCoverBuckets::make()->bucketLabel($this->bucket),
            'bucket_tone'          => GetOrganisationStockCoverBuckets::BUCKETS[$this->bucket]['tone'],
        ];
    }
}
