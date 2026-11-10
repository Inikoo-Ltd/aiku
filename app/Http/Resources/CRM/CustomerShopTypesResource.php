<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 10 Nov 2026, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Http\Resources\CRM;

use App\Enums\CRM\Customer\CustomerShopTypeEnum;
use App\Enums\CRM\Customer\CustomerShopTypeSourceEnum;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property int $id
 * @property string $slug
 * @property string $reference
 * @property string $name
 * @property ?string $company_name
 * @property mixed $location
 * @property mixed $last_invoiced_at
 * @property ?CustomerShopTypeEnum $shop_type
 * @property ?CustomerShopTypeSourceEnum $shop_type_source
 * @property ?int $number_invoices_type_invoice
 * @property mixed $sales_all
 */
class CustomerShopTypesResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id'                           => $this->id,
            'slug'                         => $this->slug,
            'reference'                    => $this->reference,
            'name'                         => $this->name,
            'company_name'                 => $this->company_name,
            'location'                     => is_string($this->location) ? json_decode($this->location, true) : $this->location,
            'last_invoiced_at'             => $this->last_invoiced_at,
            'number_invoices_type_invoice' => $this->number_invoices_type_invoice,
            'sales_all'                    => $this->sales_all,
            'shop_type'                    => $this->shop_type?->value,
            'shop_type_label'              => $this->shop_type ? CustomerShopTypeEnum::labels()[$this->shop_type->value] : null,
            'shop_type_source_label'       => $this->shop_type && $this->shop_type_source ? CustomerShopTypeSourceEnum::labels()[$this->shop_type_source->value] : null,
        ];
    }
}
