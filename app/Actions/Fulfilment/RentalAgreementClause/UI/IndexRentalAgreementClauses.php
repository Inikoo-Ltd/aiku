<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 19 Jun 2024 15:16:17 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Fulfilment\RentalAgreementClause\UI;

use App\Actions\OrgAction;
use App\Http\Resources\Fulfilment\RentalAgreementClausesResource;
use App\InertiaTable\InertiaTable;
use App\Models\Fulfilment\Fulfilment;
use App\Models\Fulfilment\FulfilmentCustomer;
use App\Models\Fulfilment\RentalAgreementClause;
use App\Models\SysAdmin\Organisation;
use App\Services\QueryBuilder;
use Closure;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;
use Spatie\QueryBuilder\AllowedFilter;

class IndexRentalAgreementClauses extends OrgAction
{
    public function authorize(ActionRequest $request): bool
    {
        $this->canEdit = $request->user()->authTo("fulfilment-shop.{$this->fulfilment->id}.edit");
        return $request->user()->authTo("fulfilment-shop.{$this->fulfilment->id}.view");
    }

    public function asController(Organisation $organisation, Fulfilment $fulfilment, FulfilmentCustomer $fulfilmentCustomer, ActionRequest $request): LengthAwarePaginator
    {
        $this->initialisationFromFulfilment($fulfilment, $request);

        return $this->handle($fulfilmentCustomer);
    }

    public function handle(FulfilmentCustomer $fulfilmentCustomer, $prefix = null): LengthAwarePaginator
    {
        $globalSearch = AllowedFilter::callback('global', function ($query, $value) {
            $query->where(function ($query) use ($value) {
                $query->whereAnyWordStartWith('assets.code', $value)
                    ->orWereAnyWordStartWith('assets.name', $value);
            });
        });

        if ($prefix) {
            InertiaTable::updateQueryBuilderParameters($prefix);
        }
        $queryBuilder = QueryBuilder::for(RentalAgreementClause::class);
        $queryBuilder->where('rental_agreement_clauses.fulfilment_customer_id', $fulfilmentCustomer->id);

        return $queryBuilder
            ->defaultSort('asset_code')
            ->select([
                'rental_agreement_clauses.id',
                'rental_agreement_clauses.percentage_off'
            ])
            ->leftJoin('assets', 'rental_agreement_clauses.asset_id', 'assets.id')
            ->addSelect(
                'assets.code as asset_code',
                'assets.name as asset_name',
                'assets.type as asset_type',
                'assets.price as asset_price',
                'assets.units as asset_units',
                'assets.unit as asset_unit'
            )
            ->allowedSorts(['id','asset_code','asset_type'])
            ->allowedFilters([$globalSearch])
            ->withPaginator($prefix, tableName: request()->route()->getName())
            ->withQueryString();
    }

    public function tableStructure(?array $modelOperations = null, $prefix = null): Closure
    {
        return function (InertiaTable $table) use ($modelOperations, $prefix) {
            if ($prefix) {
                $table
                    ->name($prefix)
                    ->pageName($prefix.'Page');
            }

            $table
                ->withGlobalSearch()
                ->withModelOperations($modelOperations)
                ->withEmptyState(
                    [
                    'title'       => __("No agreed prices found"),
                    ]
                )
            ->column(key: 'asset_code', label: __('code'), canBeHidden: false, sortable: true)
            ->column(key: 'asset_name', label: __('name'), canBeHidden: false)
            ->column(key: 'asset_type', label: __('type'), canBeHidden: false, sortable: true)
            ->column(key: 'percentage_off', label: __('discount'), canBeHidden: false)
            ->column(key: 'agreed_price', label: __('agreed price'), canBeHidden: false);
        };
    }

    public function htmlResponse(LengthAwarePaginator $clauses, ActionRequest $request): Response
    {
        return Inertia::render(
            'Org/Fulfilment/RentalAgreementClauses',
            [
                'title'       => __('Agreed Prices'),
                'pageHead'    => [
                    'title'     => __('agreed prices'),
                    'icon'      => [
                        'icon'  => ['fal', 'fa-folder-tree'],
                        'title' => __('Agreed prices')
                    ],
                ],
                'data'        => RentalAgreementClausesResource::collection($clauses),
            ]
        )->table($this->tableStructure());
    }
}
