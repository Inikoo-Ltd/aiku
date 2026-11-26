<?php

/*
 * Author: Vika Aqordi
 * Created on: 2026-11-26 14:00
 * Github: https://github.com/aqordeon
 * Copyright: 2026
 */

namespace App\Actions\SupplyChain\Supplier;

use App\Models\SupplyChain\Supplier;
use App\Models\SupplyChain\SupplierProduct;
use Illuminate\Support\Collection;
use Lorisleiva\Actions\Concerns\AsObject;

/**
 * What a supplier picker shows and searches: code, company name, contact name, nickname and the codes of the products the supplier has.
 */
class GetSupplierOptionFields
{
    use AsObject;

    /**
     * @param  Collection<int, Supplier>  $suppliers
     * @return Collection<int, array{code: string, nickname: string|null, company_name: string|null, contact_name: string|null, product_codes: string}>
     */
    public function handle(Collection $suppliers): Collection
    {
        $productCodesBySupplier = $this->productCodesBySupplier($suppliers->pluck('id')->unique()->values()->all());

        return $suppliers->mapWithKeys(fn (Supplier $supplier) => [
            $supplier->id => [
                'code'          => $supplier->code,
                'nickname'      => $supplier->nickname(),
                'company_name'  => $supplier->company_name,
                'contact_name'  => $supplier->contact_name,
                'product_codes' => $productCodesBySupplier->get($supplier->id, ''),
            ],
        ]);
    }

    /**
     * @param  array<int, int>  $supplierIds
     * @return Collection<int, string>
     */
    public function productCodesBySupplier(array $supplierIds): Collection
    {
        if (!$supplierIds) {
            return collect();
        }

        return SupplierProduct::whereIn('supplier_id', $supplierIds)
            ->groupBy('supplier_id')
            ->selectRaw("supplier_id, string_agg(distinct code, ' ' order by code) as product_codes")
            ->pluck('product_codes', 'supplier_id');
    }
}
