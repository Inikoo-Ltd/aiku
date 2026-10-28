<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 26 Oct 2026 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Http\Resources\SupplyChain;

use App\Actions\SupplyChain\SupplierSubmission\UI\ShowSupplierSubmission;
use App\Enums\SupplyChain\SupplierSubmission\SupplierSubmissionStateEnum;
use App\Enums\SupplyChain\SupplierSubmission\SupplierSubmissionTypeEnum;
use App\Models\SupplyChain\SupplierSubmission;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Arr;

/**
 * @mixin SupplierSubmission
 */
class SupplierSubmissionsResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id'            => $this->id,
            'submitted_at'  => $this->submitted_at,
            'supplier_code' => $this->supplier_code,
            'supplier_name' => $this->supplier_name,
            'supplier_slug' => $this->supplier_slug,
            'type'          => $this->type->value,
            'type_label'    => SupplierSubmissionTypeEnum::labels()[$this->type->value],
            'state'         => $this->state->value,
            'state_label'   => SupplierSubmissionStateEnum::labels()[$this->state->value],
            'summary'       => ShowSupplierSubmission::summary($this->resource, $this->supplier_product_code),
            'price_change'  => $this->type === SupplierSubmissionTypeEnum::PRICE_CHANGE ? [
                'product_code'   => $this->supplier_product_code,
                'current_cost'   => $this->supplier_product_cost,
                'proposed_price' => Arr::get($this->data ?? [], 'proposed_price'),
                'currency'       => $this->currency_code,
            ] : null,
        ];
    }
}
