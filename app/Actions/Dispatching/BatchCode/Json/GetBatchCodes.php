<?php

/*
 * Author: stewicca <stewicalf@gmail.com>
 * Created: Mon, 21 Apr 2026, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Steven Wicca Alfredo
 */

namespace App\Actions\Dispatching\BatchCode\Json;

use App\Actions\OrgAction;
use App\Http\Resources\Dispatching\BatchCodeResource;
use App\InertiaTable\InertiaTable;
use App\Models\Dispatching\BatchCode;
use App\Models\Inventory\OrgStock;
use App\Models\SysAdmin\Organisation;
use App\Services\QueryBuilder;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Lorisleiva\Actions\ActionRequest;
use Spatie\QueryBuilder\AllowedFilter;

class GetBatchCodes extends OrgAction
{
    public function asController(Organisation $organisation, OrgStock $orgStock, ActionRequest $request): LengthAwarePaginator
    {
        $this->initialisation($organisation, $request);

        return $this->handle($orgStock);
    }

    public function handle(OrgStock $orgStock, ?string $prefix = null): LengthAwarePaginator
    {
        $globalSearch = AllowedFilter::callback('global', function ($query, $value) {
            $query->whereStartWith('batch_codes.code', $value);
        });

        if ($prefix) {
            InertiaTable::updateQueryBuilderParameters($prefix);
        }

        return QueryBuilder::for(BatchCode::class)
            // ->where('batch_codes.org_stock_id', $orgStock->id) // Uncomment this to make batch_code strict with org_stock_id
            ->defaultSort('batch_codes.code')
            ->select([
                'batch_codes.id',
                'batch_codes.code',
                'batch_codes.expiry_date',
            ])
            ->allowedFilters([$globalSearch])
            ->withPaginator($prefix, tableName: request()->route()->getName())
            ->withQueryString();
    }

    public function jsonResponse(LengthAwarePaginator $batchCodes): AnonymousResourceCollection
    {
        return BatchCodeResource::collection($batchCodes);
    }
}
