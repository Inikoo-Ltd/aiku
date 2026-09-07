<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 04 Sep 2026 20:10:00 British Summer Time, Sheffield, UK
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Procurement\OrgSupplierProducts\Json;

use App\Actions\OrgAction;
use App\Models\Procurement\OrgSupplierProduct;
use App\Models\SysAdmin\Organisation;
use App\Services\QueryBuilder;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Resources\Json\JsonResource;
use Lorisleiva\Actions\ActionRequest;
use Spatie\QueryBuilder\AllowedFilter;

class GetOrgSupplierProducts extends OrgAction
{
    public function asController(Organisation $organisation, ActionRequest $request): LengthAwarePaginator
    {
        $this->initialisation($organisation, $request);

        return $this->handle($organisation);
    }

    public function handle(Organisation $organisation): LengthAwarePaginator
    {
        $globalSearch = AllowedFilter::callback('global', function ($query, $value) {
            $query->where(function ($query) use ($value) {
                $query->whereStartWith('supplier_products.code', $value)
                    ->orWhereAnyWordStartWith('supplier_products.name', $value)
                    ->orWhereAnyWordStartWith('suppliers.name', $value);
            });
        });

        return QueryBuilder::for(OrgSupplierProduct::class)
            ->where('org_supplier_products.organisation_id', $organisation->id)
            ->leftJoin('supplier_products', 'supplier_products.id', 'org_supplier_products.supplier_product_id')
            ->leftJoin('org_suppliers', 'org_suppliers.id', 'org_supplier_products.org_supplier_id')
            ->leftJoin('suppliers', 'suppliers.id', 'org_suppliers.supplier_id')
            ->select([
                'org_supplier_products.id',
                'supplier_products.code',
                'supplier_products.name',
                'suppliers.name as supplier_name',
            ])
            ->defaultSort('supplier_products.code')
            ->allowedFilters([$globalSearch])
            ->withPaginator(null, tableName: request()->route()->getName())
            ->withQueryString();
    }

    public function jsonResponse(LengthAwarePaginator $orgSupplierProducts): AnonymousResourceCollection
    {
        return JsonResource::collection($orgSupplierProducts);
    }
}
