<?php

/*
 * Author: Steven Wicca stewicalf@gmail.com
 * Created: Fri, 05 Dec 2025 14:25:19 Central Indonesia Time, Lembeng Beach, Bali, Indonesia
 * Copyright (c) 2025, Steven Wicca Alfredo
 */

namespace App\Actions\Accounting\Intrastat\UI;

use App\Actions\OrgAction;
use App\Actions\UI\Reports\IndexReports;
use App\Http\Resources\Accounting\IntrastatExportTimeSeriesRecordResource;
use App\InertiaTable\InertiaTable;
use App\Models\Accounting\IntrastatExportTimeSeriesRecord;
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

class IndexIntrastatExportReport extends OrgAction
{
    private int $records;
    private string $bucket = 'all';

    public function authorize(ActionRequest $request): bool
    {
        return in_array($this->organisation->id, $request->user()->authorisedOrganisations()->pluck('id')->toArray());
    }

    protected function getElementGroups(Organisation $organisation, ?array $dateFilter = null): array
    {
        $ordersQuery = IntrastatExportTimeSeriesRecord::where('intrastat_export_time_series_records.organisation_id', $organisation->id)
            ->where('intrastat_export_time_series_records.frequency', 'D')
            ->join('intrastat_export_time_series', 'intrastat_export_time_series_records.intrastat_export_time_series_id', '=', 'intrastat_export_time_series.id');

        $replacementsQuery = IntrastatExportTimeSeriesRecord::where('intrastat_export_time_series_records.organisation_id', $organisation->id)
            ->where('intrastat_export_time_series_records.frequency', 'D')
            ->join('intrastat_export_time_series', 'intrastat_export_time_series_records.intrastat_export_time_series_id', '=', 'intrastat_export_time_series.id');

        $withVatQuery = IntrastatExportTimeSeriesRecord::where('intrastat_export_time_series_records.organisation_id', $organisation->id)
            ->where('intrastat_export_time_series_records.frequency', 'D')
            ->join('intrastat_export_time_series', 'intrastat_export_time_series_records.intrastat_export_time_series_id', '=', 'intrastat_export_time_series.id')
            ->join('tax_categories', 'intrastat_export_time_series.tax_category_id', '=', 'tax_categories.id')
            ->whereIn('tax_categories.type', ['standard', 'special']);

        $withoutVatQuery = IntrastatExportTimeSeriesRecord::where('intrastat_export_time_series_records.organisation_id', $organisation->id)
            ->where('intrastat_export_time_series_records.frequency', 'D')
            ->join('intrastat_export_time_series', 'intrastat_export_time_series_records.intrastat_export_time_series_id', '=', 'intrastat_export_time_series.id')
            ->leftJoin('tax_categories', 'intrastat_export_time_series.tax_category_id', '=', 'tax_categories.id')
            ->where(function ($q) {
                $q->where('tax_categories.type', 'eu_vtc')
                  ->orWhereNull('intrastat_export_time_series.tax_category_id');
            });

        if ($dateFilter && !empty($dateFilter['date'])) {
            $raw = $dateFilter['date'];
            [$start, $end] = explode('-', $raw);
            $start = \Carbon\Carbon::createFromFormat('Ymd', $start)->format('Y-m-d');
            $end   = \Carbon\Carbon::createFromFormat('Ymd', $end)->format('Y-m-d');

            $ordersQuery->whereBetween('intrastat_export_time_series_records.from', [$start, $end]);
            $replacementsQuery->whereBetween('intrastat_export_time_series_records.from', [$start, $end]);
            $withVatQuery->whereBetween('intrastat_export_time_series_records.from', [$start, $end]);
            $withoutVatQuery->whereBetween('intrastat_export_time_series_records.from', [$start, $end]);
        }

        return [
            'delivery_type' => [
                'label'    => __('Delivery Type'),
                'elements' => [
                    'orders'       => [
                        __('Orders with Invoice'),
                        $ordersQuery->count()
                    ],
                    'replacements' => [
                        __('Replacements/Samples'),
                        $replacementsQuery->count(),
                        __('Replacements')
                    ],
                ],
                'engine' => function ($query, $elements) {
                    // Note: delivery_note_type filtering not available in time series
                    // This filter is currently disabled
                }
            ],
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
                    if (in_array('with_vat', $elements) && !in_array('without_vat', $elements)) {
                        $query->join('tax_categories as tc_filter', 'intrastat_export_time_series.tax_category_id', '=', 'tc_filter.id')
                              ->whereIn('tc_filter.type', ['standard', 'special']);
                    } elseif (in_array('without_vat', $elements) && !in_array('with_vat', $elements)) {
                        $query->leftJoin('tax_categories as tc_filter', 'intrastat_export_time_series.tax_category_id', '=', 'tc_filter.id')
                              ->where(function ($q) {
                                  $q->where('tc_filter.type', 'eu_vtc')
                                    ->orWhereNull('intrastat_export_time_series.tax_category_id');
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
                $query->whereWith('intrastat_export_time_series.tariff_code', $value);
            });
        });

        if ($prefix) {
            InertiaTable::updateQueryBuilderParameters($prefix);
        }

        $queryBuilder = QueryBuilder::for(IntrastatExportTimeSeriesRecord::class);
        $queryBuilder->where('intrastat_export_time_series_records.organisation_id', $organisation->id);
        $queryBuilder->where('intrastat_export_time_series_records.frequency', 'D');

        $queryBuilder->join('intrastat_export_time_series', 'intrastat_export_time_series_records.intrastat_export_time_series_id', '=', 'intrastat_export_time_series.id');
        $queryBuilder->leftJoin('countries', 'intrastat_export_time_series.country_id', '=', 'countries.id');
        $queryBuilder->leftJoin('tax_categories', 'intrastat_export_time_series.tax_category_id', '=', 'tax_categories.id');

        if ($this->bucket == 'orders') {
            // Note: delivery_note_type filtering not available in time series
        } elseif ($this->bucket == 'replacements') {
            // Note: delivery_note_type filtering not available in time series
        } elseif ($this->bucket == 'with_vat') {
            $queryBuilder->whereIn('tax_categories.type', ['standard', 'special']);
        } elseif ($this->bucket == 'without_vat') {
            $queryBuilder->where(function ($query) {
                $query->where('tax_categories.type', 'eu_vtc')
                      ->orWhereNull('intrastat_export_time_series.tax_category_id');
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

        $this->records = $queryBuilder->count('intrastat_export_time_series_records.id');

        $queryBuilder
            ->defaultSort('-intrastat_export_time_series_records.from')
            ->allowedSorts([
                AllowedSort::callback('date', function ($query, $direction) {
                    $direction = strtolower($direction);
                    if (!in_array($direction, ['asc', 'desc'])) {
                        $direction = 'desc';
                    }
                    return $query->orderBy('intrastat_export_time_series_records.from', $direction);
                }),
                AllowedSort::callback('quantity', function ($query, $direction) {
                    $direction = strtolower($direction);
                    if (!in_array($direction, ['asc', 'desc'])) {
                        $direction = 'asc';
                    }
                    return $query->orderBy('intrastat_export_time_series_records.quantity', $direction);
                }),
                AllowedSort::callback('value_org_currency', function ($query, $direction) {
                    $direction = strtolower($direction);
                    if (!in_array($direction, ['asc', 'desc'])) {
                        $direction = 'asc';
                    }
                    return $query->orderBy('intrastat_export_time_series_records.value_org_currency', $direction);
                }),
                AllowedSort::callback('weight', function ($query, $direction) {
                    $direction = strtolower($direction);
                    if (!in_array($direction, ['asc', 'desc'])) {
                        $direction = 'asc';
                    }
                    return $query->orderBy('intrastat_export_time_series_records.weight', $direction);
                }),
            ])
            ->allowedFilters([$globalSearch])
            ->withBetweenDates(['from'])
            ->withPaginator($prefix)
            ->withQueryString();

        return $queryBuilder
            ->select([
                'intrastat_export_time_series_records.id',
                'intrastat_export_time_series_records.from as date',
                'intrastat_export_time_series.tariff_code',
                'intrastat_export_time_series.country_id',
                'intrastat_export_time_series.tax_category_id',
                'intrastat_export_time_series_records.quantity',
                'intrastat_export_time_series_records.value_org_currency',
                'intrastat_export_time_series_records.weight',
                'intrastat_export_time_series_records.delivery_notes_count',
                'intrastat_export_time_series_records.products_count',
                'countries.name as country_name',
                'countries.code as country_code',
                'tax_categories.name as tax_category_name',
                DB::raw("'" . $organisation->currency->code . "' as currency_code"),
                'intrastat_export_time_series_records.invoices_count',
                'intrastat_export_time_series_records.partner_tax_numbers',
                'intrastat_export_time_series_records.valid_tax_numbers_count',
                'intrastat_export_time_series_records.invalid_tax_numbers_count',
                'intrastat_export_time_series_records.delivery_note_type',
                'intrastat_export_time_series_records.mode_of_transport',
                'intrastat_export_time_series_records.delivery_terms',
                'intrastat_export_time_series_records.nature_of_transaction'
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
                        'title'       => __('No Intrastat export data'),
                        'description' => __('No EU delivery data available for the selected period'),
                        'count'       => $this->records,
                    ]
                )
                ->betweenDates(['from']);

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
                ->column(key: 'delivery_type', label: __('Type'))
                ->column(key: 'tax_category', label: __('VAT Category'))
                ->column(key: 'invoices', label: __('Invoices'), type: 'number')
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
            'Org/Accounting/Intrastat/IntrastatExportReport',
            [
                'breadcrumbs' => $this->getBreadcrumbs($request->route()->getName(), $request->route()->originalParameters()),
                'title'       => __('Intrastat Export Report'),
                'pageHead'    => [
                    'title' => __('Intrastat Export Report'),
                    'icon'  => [
                        'title' => __('Intrastat Exports'),
                        'icon'  => 'fal fa-file-export'
                    ],
                ],
                'data'        => IntrastatExportTimeSeriesRecordResource::collection($metrics),
                'filters'     => [
                    'countries' => $euCountries,
                ],
            ]
        )->table($this->tableStructure($this->organisation, null, null, $this->bucket, $dateFilter));
    }

    public function jsonResponse(LengthAwarePaginator $metrics): AnonymousResourceCollection
    {
        return IntrastatExportTimeSeriesRecordResource::collection($metrics);
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
                        'label' => __('Intrastat Exports'),
                        'route' => [
                            'name'       => 'grp.org.reports.intrastat.exports',
                            'parameters' => $routeParameters
                        ]
                    ]
                ],
            ],
        );
    }
}
