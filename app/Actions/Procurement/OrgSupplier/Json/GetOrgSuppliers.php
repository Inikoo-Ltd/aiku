<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 01 Oct 2026 10:00:00 Central European Summer Time, Mijas, Spain
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Procurement\OrgSupplier\Json;

use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\WithProcurementAuthorisation;
use App\Models\Procurement\OrgSupplier;
use App\Models\SysAdmin\Organisation;
use App\Services\QueryBuilder;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Resources\Json\JsonResource;
use Lorisleiva\Actions\ActionRequest;
use Spatie\QueryBuilder\AllowedFilter;

class GetOrgSuppliers extends OrgAction
{
    use WithProcurementAuthorisation;

    public function asController(Organisation $organisation, ActionRequest $request): LengthAwarePaginator
    {
        $this->initialisation($organisation, $request);

        return $this->handle($organisation);
    }

    public function handle(Organisation $organisation): LengthAwarePaginator
    {
        $globalSearch = AllowedFilter::callback('global', function ($query, $value) {
            $query->where(function ($query) use ($value) {
                $query->whereStartWith('suppliers.code', $value)
                    ->orWhereAnyWordStartWith('suppliers.name', $value);
            });
        });

        return QueryBuilder::for(OrgSupplier::class)
            ->where('org_suppliers.organisation_id', $organisation->id)
            ->where('org_suppliers.status', true)
            ->leftJoin('suppliers', 'suppliers.id', 'org_suppliers.supplier_id')
            ->leftJoin('currencies', 'currencies.id', 'suppliers.currency_id')
            ->select([
                'org_suppliers.id',
                'suppliers.code',
                'suppliers.name',
                'currencies.code as currency_code',
            ])
            ->defaultSort('suppliers.code')
            ->allowedFilters([$globalSearch])
            ->withPaginator(null, tableName: request()->route()->getName())
            ->withQueryString();
    }

    public function jsonResponse(LengthAwarePaginator $orgSuppliers): AnonymousResourceCollection
    {
        return JsonResource::collection($orgSuppliers);
    }
}
