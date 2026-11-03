<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 03 Nov 2026 14:30:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Http\Resources\Production;

use App\Models\Production\ManufacturePayrollAdjustment;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin ManufacturePayrollAdjustment
 */
class ManufacturePayrollAdjustmentResource extends JsonResource
{
    public function toArray($request): array
    {
        return $this->resource->toPayrollChange(true, $request->user()) + [
            'created_at' => $this->created_at,
        ];
    }
}
