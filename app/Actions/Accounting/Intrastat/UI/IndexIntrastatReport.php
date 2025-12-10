<?php

/*
 * Author: Steven Wicca stewicalf@gmail.com
 * Created: Fri, 05 Dec 2025 14:25:19 Central Indonesia Time, Lembeng Beach, Bali, Indonesia
 * Copyright (c) 2025, Steven Wicca Alfredo
 */

namespace App\Actions\Accounting\Intrastat\UI;

use App\Actions\OrgAction;
use App\Http\Resources\Accounting\IntrastatMetricsResource;
use App\InertiaTable\InertiaTable;
use App\Models\Accounting\IntrastatMetrics;
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

class IndexIntrastatReport extends OrgAction
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
                $query->whereWith('intrastat_metrics.tariff_code', $value);
            });
        });

        if ($prefix) {
            InertiaTable::updateQueryBuilderParameters($prefix);
        }

        $queryBuilder = QueryBuilder::for(IntrastatMetrics::class);
        $queryBuilder->where('intrastat_metrics.organisation_id', $organisation->id);

        $queryBuilder->leftJoin('countries', 'intrastat_metrics.country_id', '=', 'countries.id');
        $queryBuilder->leftJoin('tax_categories', 'intrastat_metrics.tax_category_id', '=', 'tax_categories.id');

        $this->records = $queryBuilder->count('intrastat_metrics.id');

        $queryBuilder
            ->defaultSort('-date')
            ->allowedSorts(['date', 'tariff_code', 'quantity', 'value_org_currency', 'weight'])
            ->allowedFilters([$globalSearch])
            ->withBetweenDates(['date'])
            ->withPaginator($prefix)
            ->withQueryString();

        return $queryBuilder
            ->select([
                'intrastat_metrics.id',
                'intrastat_metrics.date',
                'intrastat_metrics.tariff_code',
                'intrastat_metrics.country_id',
                'intrastat_metrics.tax_category_id',
                'intrastat_metrics.quantity',
                'intrastat_metrics.value_org_currency',
                'intrastat_metrics.weight',
                'intrastat_metrics.delivery_notes_count',
                'intrastat_metrics.products_count',
                'countries.name as country_name',
                'countries.code as country_code',
                'tax_categories.name as tax_category_name',
                DB::raw("'" . $organisation->currency->code . "' as currency_code")
            ])
            ->paginate(perPage: 50);
    }

    public function tableStructure(Organisation $organisation, ?array $exportLinks = null, $prefix = null): Closure
    {
        return function (InertiaTable $table) use ($organisation, $exportLinks, $prefix) {
            if ($prefix) {
                $table->name($prefix)->pageName($prefix.'Page');
            }

            $table
                ->withGlobalSearch()
                ->withEmptyState(
                    [
                        'title'       => __('No Intrastat data'),
                        'description' => __('No EU delivery data available for the selected period'),
                        'count'       => $this->records,
                    ]
                )
                ->betweenDates(['date'])
                ->column(key: 'date', label: __('Date'), sortable: true)
                ->column(key: 'tariff_code', label: __('Tariff Code'), sortable: true, searchable: true)
                ->column(key: 'country', label: __('Destination'))
                ->column(key: 'tax_category', label: __('VAT Category'))
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
            'Org/Accounting/Intrastat/IntrastatReport',
            [
                'breadcrumbs' => $this->getBreadcrumbs($request->route()->originalParameters()),
                'title'       => __('Intrastat Report'),
                'pageHead'    => [
                    'title' => __('Intrastat Export Report'),
                    'icon'  => [
                        'title' => __('Intrastat'),
                        'icon'  => 'fal fa-file-export'
                    ],
                ],
                'data'        => IntrastatMetricsResource::collection($metrics),
                'filters'     => [
                    'countries' => $euCountries,
                ],
            ]
        )->table($this->tableStructure($this->organisation));
    }

    public function jsonResponse(LengthAwarePaginator $metrics): AnonymousResourceCollection
    {
        return IntrastatMetricsResource::collection($metrics);
    }

    public function getBreadcrumbs(array $routeParameters): array
    {
        return [
            [
                'type'   => 'simple',
                'simple' => [
                    'icon'  => 'fal fa-chart-line',
                    'label' => __('Reports'),
                    'route' => [
                        'name'       => 'grp.org.reports.index',
                        'parameters' => $routeParameters
                    ]
                ]
            ],
            [
                'type'   => 'simple',
                'simple' => [
                    'icon'  => 'fal fa-file-export',
                    'label' => __('Intrastat'),
                    'route' => [
                        'name'       => 'grp.org.reports.intrastat',
                        'parameters' => $routeParameters
                    ]
                ]
            ],
        ];
    }
}
