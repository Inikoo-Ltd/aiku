<?php

/*
 * Author: Steven Wicca stewicalf@gmail.com
 * Created: Fri, 05 Dec 2025 14:25:19 Central Indonesia Time, Lembeng Beach, Bali, Indonesia
 * Copyright (c) 2025, Steven Wicca Alfredo
 */

namespace App\Actions\Accounting\Intrastat\UI;

use App\Actions\OrgAction;
use App\Actions\UI\Reports\IndexReports;
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
    private string $bucket = 'all';

    public function authorize(ActionRequest $request): bool
    {
        return in_array($this->organisation->id, $request->user()->authorisedOrganisations()->pluck('id')->toArray());
    }

    protected function getElementGroups(Organisation $organisation, ?array $dateFilter = null): array
    {
        $withVatQuery = IntrastatMetrics::where('organisation_id', $organisation->id)
            ->whereHas('taxCategory', function ($query) {
                $query->where('rate', '>', 0.0);
            });

        $withoutVatQuery = IntrastatMetrics::where('organisation_id', $organisation->id)
            ->where(function ($query) {
                $query->whereHas('taxCategory', function ($q) {
                    $q->where('rate', '=', 0.0);
                })
                ->orWhereNull('tax_category_id');
            });

        if ($dateFilter && !empty($dateFilter['date'])) {
            $raw = $dateFilter['date'];
            [$start, $end] = explode('-', $raw);
            $start = \Carbon\Carbon::createFromFormat('Ymd', $start)->format('Y-m-d');
            $end   = \Carbon\Carbon::createFromFormat('Ymd', $end)->format('Y-m-d');

            $withVatQuery->whereBetween('date', [$start, $end]);
            $withoutVatQuery->whereBetween('date', [$start, $end]);
        }

        return [
            'vat_status' => [
                'label'    => __('VAT Status'),
                'elements' => [
                    'with_vat'    => [
                        __('Invoices with VAT'),
                        $withVatQuery->count()
                    ],
                    'without_vat' => [
                        __('Invoices with no VAT'),
                        $withoutVatQuery->count(),
                        __('No VAT')
                    ],
                ],
                'engine' => function ($query, $elements) {
                    if (in_array('with_vat', $elements)) {
                        $query->whereHas('taxCategory', function ($q) {
                            $q->where('rate', '>', 0.0);
                        });
                    }
                    if (in_array('without_vat', $elements)) {
                        $query->where(function ($q) {
                            $q->whereHas('taxCategory', function ($subQuery) {
                                $subQuery->where('rate', '=', 0.0);
                            })
                            ->orWhereNull('tax_category_id');
                        });
                    }
                }
            ],
        ];
    }

    public function handle(Organisation $organisation, $prefix = null, $bucket = null, $dateFilter = null): LengthAwarePaginator
    {
        if ($bucket) {
            $this->bucket = $bucket;
        }

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

        if ($this->bucket == 'with_vat') {
            $queryBuilder->whereHas('taxCategory', function ($query) {
                $query->where('rate', '>', 0.0);
            });
        } elseif ($this->bucket == 'without_vat') {
            $queryBuilder->where(function ($query) {
                $query->whereHas('taxCategory', function ($q) {
                    $q->where('rate', '=', 0.0);
                })
                ->orWhereNull('tax_category_id');
            });
        } elseif ($this->bucket == 'all') {
            foreach ($this->getElementGroups($organisation, $dateFilter) as $key => $elementGroup) {
                $queryBuilder->whereElementGroup(
                    key: $key,
                    allowedElements: array_keys($elementGroup['elements']),
                    engine: $elementGroup['engine'],
                    prefix: $prefix
                );
            }
        }

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

    public function tableStructure(Organisation $organisation, ?array $exportLinks = null, $prefix = null, $bucket = null, $dateFilter = null): Closure
    {
        return function (InertiaTable $table) use ($organisation, $exportLinks, $prefix, $bucket, $dateFilter) {
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
                ->betweenDates(['date']);

            if ($bucket == 'all') {
                foreach ($this->getElementGroups($organisation, $dateFilter) as $key => $elementGroup) {
                    $table->elementGroup(
                        key: $key,
                        label: $elementGroup['label'],
                        elements: $elementGroup['elements']
                    );
                }
            }

            $table
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
        $dateFilter = $request->input('between', []);

        return $this->handle($organisation, null, 'all', $dateFilter);
    }

    public function inReports(Organisation $organisation): int
    {
        $this->handle($organisation, null, 'all');

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

        $dateFilter = $request->input('between', []);

        return Inertia::render(
            'Org/Accounting/Intrastat/IntrastatReport',
            [
                'breadcrumbs' => $this->getBreadcrumbs($request->route()->getName(), $request->route()->originalParameters()),
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
        )->table($this->tableStructure($this->organisation, null, null, $this->bucket, $dateFilter));
    }

    public function jsonResponse(LengthAwarePaginator $metrics): AnonymousResourceCollection
    {
        return IntrastatMetricsResource::collection($metrics);
    }

    public function getBreadcrumbs(string $routeName, array $routeParameters): array
    {
        return array_merge(
            IndexReports::make()->getBreadcrumbs($routeName, $routeParameters),
            [
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
            ],
        );
    }
}
