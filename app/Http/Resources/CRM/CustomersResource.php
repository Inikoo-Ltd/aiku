<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 02 Apr 2024 14:30:26 Central Indonesia Time, Bali Office , Indonesia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Http\Resources\CRM;

use App\Http\Resources\HasSelfCall;
use App\Http\Resources\Helpers\AddressResource;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property int $number_current_customer_clients
 * @property mixed $slug
 * @property mixed $reference
 * @property mixed $name
 * @property mixed $contact_name
 * @property mixed $company_name
 * @property mixed $location
 * @property mixed $email
 * @property mixed $created_at
 * @property mixed $last_invoiced_at
 * @property mixed $sales_all
 * @property mixed $sales_org_currency_all
 * @property mixed $sales_grp_currency_all
 * @property mixed $number_invoices_type_invoice
 * @property mixed $number_current_portfolios
 * @property mixed $currency_code
 * @property bool $is_dropshipping
 * @property mixed $organisation_name
 * @property mixed $phone
 * @property mixed $state
 * @property mixed $status
 * @property mixed $address
 * @property mixed $shop_name
 */
class CustomersResource extends JsonResource
{
    use HasSelfCall;

    public function toArray($request): array
    {
        $data = [
            'slug'                            => $this->slug,
            'reference'                       => $this->reference,
            'name'                            => $this->name,
            'email'                           => $this->email,
            'phone'                           => $this->phone,
            'state'                           => $this->state,
            'status'                          => $this->status,
            'approved_at'                     => $this->approved_at ?? null,
            'address'                         => AddressResource::make($this->address),
            'is_dropshipping'                 => $this->is_dropshipping,
            'contact_name'                    => $this->contact_name,
            'company_name'                    => $this->company_name,
            'location'                        => $this->location,
            'created_at'                      => $this->created_at,
            'number_current_customer_clients' => $this->number_current_customer_clients,
            'number_current_portfolios'       => $this->number_current_portfolios,
            'platform_name'                   => $this->platform_name ?? 'none',
            'last_invoiced_at'                => $this->last_invoiced_at,
            'number_invoices_type_invoice'    => $this->number_invoices_type_invoice,
            'sales_all'                       => $this->sales_all,
            'sales_org_currency_all'          => $this->sales_org_currency_all,
            'sales_grp_currency_all'          => $this->sales_grp_currency_all,
            'currency_code'                   => $this->currency_code,
        ];

        if ($this->organisation_name) {
            data_set($data, 'organisation_name', $this->organisation_name);
            data_set($data, 'shop_name', $this->shop_name);
            data_set($data, 'organisation_slug', $this->organisation_name);
            data_set($data, 'shop_slug', $this->shop_name);
            data_forget($data, 'location');
        } elseif ($this->shop_name) {
            data_set($data, 'shop_name', $this->shop_name);
            data_set($data, 'organisation_slug', $this->organisation_name);
            data_set($data, 'shop_slug', $this->shop_name);
            data_forget($data, 'location');
        }


        return $data;
    }
}
