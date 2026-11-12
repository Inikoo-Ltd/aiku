<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 11 Aug 2024 12:50:56 Central Indonesia Time, Bali, Indonesia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Http\Resources\Procurement;

use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property int $id
 * @property string $code
 * @property string $name
 * @property string $slug
 * @property string|null $supplier_name
 * @property string|null $org_supplier_slug
 * @property int $cost
 * @property string $currency_code
 * @property int|null $supplier_product_id
 * @property string|null $internal_code
 * @property numeric|null $cbm
 * @property int|null $lead_time_days
 * @property int|null $moq
 * @property string|null $availability_status
 * @property numeric|null $completeness
 */
class OrgSupplierProductsResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id'                => $this->id,
            'code'              => $this->code,
            'name'              => $this->name,
            'slug'              => $this->slug,
            'supplier_name'     => $this->supplier_name,
            'org_supplier_slug' => $this->org_supplier_slug,
            'cost'              => $this->cost,
            'currency_code'     => $this->currency_code,
            'group_cost'        => $this->group_cost,
            'group_currency_code' => $this->group_currency_code,
            'supplier_product_id' => $this->supplier_product_id,
            'internal_code'     => $this->internal_code,
            'group_sko_code'    => $this->group_sko_code,
            'cbm'               => $this->cbm !== null ? (float) $this->cbm : null,
            'lead_time_days'    => $this->lead_time_days,
            'moq'               => $this->moq,
            'availability_status' => $this->availability_status,
            'completeness'      => $this->completeness !== null ? (int) $this->completeness : null,
            'completeness_checks' => $this->completeness_checks ? json_decode($this->completeness_checks, true) : null,
        ];
    }
}
