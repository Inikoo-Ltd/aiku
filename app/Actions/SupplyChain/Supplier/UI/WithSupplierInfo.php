<?php

namespace App\Actions\SupplyChain\Supplier\UI;

use App\Models\Helpers\Country;
use App\Models\SupplyChain\Supplier;
use Illuminate\Support\Arr;

trait WithSupplierInfo
{
    /**
     * @return array<string, mixed>
     */
    protected function supplierInfo(Supplier $supplier): array
    {
        $originCountryId = Arr::get($supplier->settings, 'default_product_country_origin');

        return array_filter([
            'delivery_type'           => Arr::get($supplier->data, 'delivery_type'),
            'delivery_time'           => Arr::get($supplier->data, 'delivery_time'),
            'production_waiting_time' => Arr::get($supplier->data, 'production_waiting_time'),
            'incoterm'                => Arr::get($supplier->data, 'incoterm'),
            'port_of_export'          => Arr::get($supplier->data, 'port_of_export'),
            'port_of_import'          => Arr::get($supplier->data, 'port_of_import'),
            'products_origin'         => $originCountryId ? Country::find($originCountryId)?->name : null,
            'payment_terms'           => Arr::get($supplier->settings, 'payment_terms'),
            'minimum_order'           => Arr::get($supplier->settings, 'minimum_order'),
            'cooling_period'          => Arr::get($supplier->settings, 'cooling_period'),
            'order_number_prefix'     => Arr::get($supplier->settings, 'order_number_prefix'),
        ], fn ($value) => $value !== null && $value !== '');
    }
}
