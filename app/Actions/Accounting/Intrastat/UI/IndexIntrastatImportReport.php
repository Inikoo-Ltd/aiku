<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 18 Dec 2024 00:50:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Accounting\Intrastat\UI;

use App\Actions\OrgAction;
use App\Actions\UI\Reports\IndexReports;
use App\Http\Resources\Accounting\IntrastatImportTimeSeriesRecordResource;
use App\InertiaTable\InertiaTable;
use App\Models\Accounting\IntrastatImportTimeSeriesRecord;
use App\Models\Helpers\Country;
use App\Models\SysAdmin\Organisation;
use App\Services\QueryBuilder;
use Closure;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\AllowedSort;

class IndexIntrastatImportReport extends OrgAction
{
    private int $records;

    public function authorize(ActionRequest $request): bool
    {
        return in_array($this->organisation->id, $request->user()->authorisedOrganisations()->pluck('id')->toArray());
    }

    public function handle(Organisation $organisation, $prefix = null): LengthAwarePaginator
    {
        $globalSearch = AllowedFilter::callback('global', function ($query, $value) {
            $query->where(function ($query) use ($value) {
                $query->whereWith('intrastat_import_time_series.tariff_code', $value);
            });
        });

        if ($prefix) {
            InertiaTable::updateQueryBuilderParameters($prefix);
        }

        $queryBuilder = QueryBuilder::for(IntrastatImportTimeSeriesRecord::class);
        $queryBuilder->where('intrastat_import_time_series_records.organisation_id', $organisation->id);
        $queryBuilder->where('intrastat_import_time_series_records.frequency', 'D');

        $queryBuilder->join('intrastat_import_time_series', 'intrastat_import_time_series_records.intrastat_import_time_series_id', '=', 'intrastat_import_time_series.id');
        $queryBuilder->leftJoin('countries', 'intrastat_import_time_series.country_id', '=', 'countries.id');
        $queryBuilder->leftJoin('tax_categories', 'intrastat_import_time_series.tax_category_id', '=', 'tax_categories.id');

        $this->records = $queryBuilder->count('intrastat_import_time_series_records.id');

        $queryBuilder
            ->defaultSort('-intrastat_import_time_series_records.from')
            ->allowedSorts([
                AllowedSort::callback('date', function ($query, $direction) {
                    $direction = strtolower($direction);
                    if (!in_array($direction, ['asc', 'desc'])) {
                        $direction = 'desc';
                    }
                    return $query->orderBy('intrastat_import_time_series_records.from', $direction);
                }),
                AllowedSort::callback('quantity', function ($query, $direction) {
                    $direction = strtolower($direction);
                    if (!in_array($direction, ['asc', 'desc'])) {
                        $direction = 'asc';
                    }
                    return $query->orderBy('intrastat_import_time_series_records.quantity', $direction);
                }),
                AllowedSort::callback('value_org_currency', function ($query, $direction) {
                    $direction = strtolower($direction);
                    if (!in_array($direction, ['asc', 'desc'])) {
                        $direction = 'asc';
                    }
                    return $query->orderBy('intrastat_import_time_series_records.value_org_currency', $direction);
                }),
                AllowedSort::callback('weight', function ($query, $direction) {
                    $direction = strtolower($direction);
                    if (!in_array($direction, ['asc', 'desc'])) {
                        $direction = 'asc';
                    }
                    return $query->orderBy('intrastat_import_time_series_records.weight', $direction);
                }),
            ])
            ->allowedFilters([$globalSearch])
            ->withBetweenDates(['from'])
            ->withPaginator($prefix)
            ->withQueryString();

        return $queryBuilder
            ->select([
                'intrastat_import_time_series_records.id',
                'intrastat_import_time_series_records.from as date',
                'intrastat_import_time_series.tariff_code',
                'intrastat_import_time_series.country_id',
                'intrastat_import_time_series.tax_category_id',
                'intrastat_import_time_series_records.quantity',
                'intrastat_import_time_series_records.value_org_currency',
                'intrastat_import_time_series_records.weight',
                'intrastat_import_time_series_records.supplier_deliveries_count',
                'intrastat_import_time_series_records.parts_count',
                'intrastat_import_time_series_records.invoices_count',
                'intrastat_import_time_series_records.valid_tax_numbers_count',
                'intrastat_import_time_series_records.invalid_tax_numbers_count',
                'countries.name as country_name',
                'countries.code as country_code',
                'tax_categories.name as tax_category_name',
                DB::raw("'" . $organisation->currency->code . "' as currency_code"),
                DB::raw("NULL as supplier_tax_numbers"),
                DB::raw("NULL as mode_of_transport"),
                DB::raw("NULL as delivery_terms"),
                DB::raw("NULL as nature_of_transaction")
            ])
            ->paginate(perPage: 50);
    }

    public function tableStructure(Organisation $organisation, ?array $exportLinks = null, $prefix = null): Closure
    {
        return function (InertiaTable $table) use ($prefix) {
            if ($prefix) {
                $table->name($prefix)->pageName($prefix.'Page');
            }

            $table
                ->withGlobalSearch()
                ->withEmptyState(
                    [
                        'title'       => __('No Intrastat import data'),
                        'description' => __('No EU supplier delivery data available for the selected period'),
                        'count'       => $this->records,
                    ]
                )
                ->betweenDates(['from']);

            $table
                ->column(key: 'date', label: __('Date'), sortable: true)
                ->column(key: 'tariff_code', label: __('Tariff Code'), sortable: true, searchable: true)
                ->column(key: 'country', label: __('Origin'))
                ->column(key: 'tax_category', label: __('VAT Category'))
                ->column(key: 'deliveries', label: __('Deliveries'), type: 'number')
                ->column(key: 'quantity', label: __('Quantity'), sortable: true, type: 'number')
                ->column(key: 'value_org_currency', label: __('Value'), sortable: true, type: 'currency')
                ->column(key: 'weight', label: __('Weight (kg)'), sortable: true, type: 'number')
                ->defaultSort('-date');
        };
    }

    public function asController(Organisation $organisation, ActionRequest $request): LengthAwarePaginator
    {
        $this->initialisation($organisation, $request);

        return $this->handle($organisation);
    }

    public function inReports(Organisation $organisation): int
    {
        $this->handle($organisation);

        return $this->records;
    }

    public function htmlResponse(LengthAwarePaginator $metrics, ActionRequest $request): Response
    {
        $euCountries = Country::whereIn('code', Country::getCountryCodesInEU())->get()->map(function ($country) {
            return [
                'id'   => $country->id,
                'name' => $country->name,
                'code' => $country->code,
            ];
        });

        return Inertia::render(
            'Org/Accounting/Intrastat/IntrastatImportReport',
            [
                'breadcrumbs' => $this->getBreadcrumbs($request->route()->getName(), $request->route()->originalParameters()),
                'title'       => __('Intrastat Import Report'),
                'pageHead'    => [
                    'title' => __('Intrastat Import Report'),
                    'icon'  => [
                        'title' => __('Intrastat Imports'),
                        'icon'  => 'fal fa-file-import'
                    ],
                ],
                'data'        => IntrastatImportTimeSeriesRecordResource::collection($metrics),
                'filters'     => [
                    'countries' => $euCountries,
                ],
            ]
        )->table($this->tableStructure($this->organisation));
    }

    public function jsonResponse(LengthAwarePaginator $metrics): AnonymousResourceCollection
    {
        return IntrastatImportTimeSeriesRecordResource::collection($metrics);
    }

    public function getBreadcrumbs(string $routeName, array $routeParameters): array
    {
        return array_merge(
            IndexReports::make()->getBreadcrumbs($routeName, $routeParameters),
            [
                [
                    'type'   => 'simple',
                    'simple' => [
                        'icon'  => 'fal fa-file-import',
                        'label' => __('Intrastat Imports'),
                        'route' => [
                            'name'       => 'grp.org.reports.intrastat.imports',
                            'parameters' => $routeParameters
                        ]
                    ]
                ],
            ],
        );
    }
}
