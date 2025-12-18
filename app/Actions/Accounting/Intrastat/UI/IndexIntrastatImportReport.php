<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 18 Dec 2024 00:50:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Accounting\Intrastat\UI;

use App\Actions\OrgAction;
use App\Actions\UI\Reports\IndexReports;
use App\Http\Resources\Accounting\IntrastatImportMetricsResource;
use App\InertiaTable\InertiaTable;
use App\Models\Accounting\IntrastatImportMetrics;
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
                $query->whereWith('intrastat_import_metrics.tariff_code', $value);
            });
        });

        if ($prefix) {
            InertiaTable::updateQueryBuilderParameters($prefix);
        }

        $queryBuilder = QueryBuilder::for(IntrastatImportMetrics::class);
        $queryBuilder->where('intrastat_import_metrics.organisation_id', $organisation->id);

        $queryBuilder->leftJoin('countries', 'intrastat_import_metrics.country_id', '=', 'countries.id');
        $queryBuilder->leftJoin('tax_categories', 'intrastat_import_metrics.tax_category_id', '=', 'tax_categories.id');

        $this->records = $queryBuilder->count('intrastat_import_metrics.id');

        $queryBuilder
            ->defaultSort('-date')
            ->allowedSorts(['date', 'tariff_code', 'quantity', 'value_org_currency', 'weight'])
            ->allowedFilters([$globalSearch])
            ->withBetweenDates(['date'])
            ->withPaginator($prefix)
            ->withQueryString();

        return $queryBuilder
            ->select([
                'intrastat_import_metrics.id',
                'intrastat_import_metrics.date',
                'intrastat_import_metrics.tariff_code',
                'intrastat_import_metrics.country_id',
                'intrastat_import_metrics.tax_category_id',
                'intrastat_import_metrics.quantity',
                'intrastat_import_metrics.value_org_currency',
                'intrastat_import_metrics.weight',
                'intrastat_import_metrics.supplier_deliveries_count',
                'intrastat_import_metrics.parts_count',
                'intrastat_import_metrics.invoices_count',
                'intrastat_import_metrics.supplier_tax_numbers',
                'intrastat_import_metrics.valid_tax_numbers_count',
                'intrastat_import_metrics.invalid_tax_numbers_count',
                'intrastat_import_metrics.mode_of_transport',
                'intrastat_import_metrics.delivery_terms',
                'intrastat_import_metrics.nature_of_transaction',
                'countries.name as country_name',
                'countries.code as country_code',
                'tax_categories.name as tax_category_name',
                DB::raw("'" . $organisation->currency->code . "' as currency_code")
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
                ->betweenDates(['date']);

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
                'data'        => IntrastatImportMetricsResource::collection($metrics),
                'filters'     => [
                    'countries' => $euCountries,
                ],
            ]
        )->table($this->tableStructure($this->organisation));
    }

    public function jsonResponse(LengthAwarePaginator $metrics): AnonymousResourceCollection
    {
        return IntrastatImportMetricsResource::collection($metrics);
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
