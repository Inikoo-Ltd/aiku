<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 25 Jun 2024 22:26:51 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Http\Resources\Procurement;

use App\Models\Procurement\OrgSupplier;
use Illuminate\Http\Resources\Json\JsonResource;

class OrgSupplierResource extends JsonResource
{
    public function toArray($request): array
    {
        /** @var OrgSupplier $orgSupplier */
        $orgSupplier = $this;

        return [
            'code' => $orgSupplier->supplier->code,
            'name' => $orgSupplier->supplier->name,
            'contact_name' => $orgSupplier->supplier->contact_name,
            'company_name' => $orgSupplier->supplier->company_name,
            'email' => $orgSupplier->supplier->email,
        ];
    }
}
