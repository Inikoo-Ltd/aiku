<?php

/*
 * Author: stewicca <stewicalf@gmail.com>
 * Copyright (c) 2026, Steven Wicca Alfredo
 */

namespace App\Actions\SalesChannels\UI;

use App\Actions\OrgAction;
use App\Actions\UI\Dashboards\ShowGroupDashboard;
use App\Enums\Helpers\TimeSeries\TimeSeriesFrequencyEnum;
use App\Http\Resources\SalesChannels\SalesChannelsResource;
use App\InertiaTable\InertiaTable;
use App\Models\Ordering\SalesChannel;
use App\Models\SysAdmin\Group;
use App\Services\QueryBuilder;
use Closure;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;
use Spatie\QueryBuilder\AllowedFilter;

class IndexSalesChannels extends OrgAction
{
    public function handle(Group $group, ?string $prefix = null): LengthAwarePaginator
    {
        $globalSearch = AllowedFilter::callback('global', function ($query, $value) {
            $query->where(function ($query) use ($value) {
                $query->whereAnyWordStartWith('name', $value);
                $query->orWhereStartWith('code', $value);
            });
        });

        if ($prefix) {
            InertiaTable::updateQueryBuilderParameters($prefix);
        }

        $query = QueryBuilder::for(SalesChannel::class);
        $query->where('group_id', $group->id);

        $selects = [
            'id',
            'name',
            'slug',
            'code',
            'type',
            'is_active',
            'show_in_dashboard'
        ];

        $timeSeriesData = $query->withTimeSeriesAggregation(
            timeSeriesTable: 'sales_channel_time_series',
            timeSeriesRecordsTable: 'sales_channel_time_series_records',
            foreignKey: 'sales_channel_id',
            aggregateColumns: [
                'refunds'            => 'refunds',
                'invoices'           => 'invoices',
                'sales_grp_currency' => 'sales',
            ],
            frequency: TimeSeriesFrequencyEnum::DAILY->value,
            includeLY: false,
        );

        $selects[] = $timeSeriesData['selectRaw']['refunds'];
        $selects[] = $timeSeriesData['selectRaw']['invoices'];
        $selects[] = $timeSeriesData['selectRaw']['sales'];

        return $query->defaultSort('id')
            ->allowedSorts(['id', 'name', 'code', 'type', 'is_active', 'refunds', 'invoices', 'sales'])
            ->allowedFilters([$globalSearch, 'name', 'code'])
            ->withPaginator($prefix)
            ->withQueryString();
    }

    public function tableStructure(?string $prefix = null): Closure
    {
        return function (InertiaTable $table) use ($prefix) {
            if ($prefix) {
                $table->name($prefix)->pageName($prefix.'Page');
            }

            $table->withGlobalSearch();
            $table->betweenDates(['date']);

            $table->column(key: 'is_active', label: '', canBeHidden: false, type: 'icon');
            $table->column(key: 'show_in_dashboard', label: '', canBeHidden: false, type: 'icon');
            $table->column(key: 'name', label: __('Name'), canBeHidden: false, sortable: true, searchable: true);
            $table->column(key: 'type', label: __('Type'), canBeHidden: false, sortable: true, searchable: true);
            $table->column(key: 'refunds', label: __('Refunds'), canBeHidden: false, sortable: true, searchable: true, align: 'right');
            $table->column(key: 'invoices', label: __('Invoices'), canBeHidden: false, sortable: true, searchable: true, align: 'right');
            $table->column(key: 'sales', label: __('Sales'), canBeHidden: false, sortable: true, searchable: true, align: 'right');
            $table->defaultSort('id');
        };
    }

    public function htmlResponse(LengthAwarePaginator $salesChannels): Response
    {
        return Inertia::render(
            'SalesChannels/SalesChannels',
            [
                'breadcrumbs' => $this->getBreadcrumbs(),
                'title'       => __('Sales Channels'),
                'pageHead'    => [
                    'title' => __('Sales Channels')
                ],
                'data'        => SalesChannelsResource::collection($salesChannels)
            ]
        )->table($this->tableStructure());
    }

    public function asController(ActionRequest $request): LengthAwarePaginator
    {
        $this->initialisationFromGroup(app('group'), $request);

        return $this->handle($this->group);
    }

    public function getBreadcrumbs(): array
    {
        return array_merge(
            ShowGroupDashboard::make()->getBreadcrumbs(),
            [
                [
                    'type'   => 'simple',
                    'simple' => [
                        'label' => __('Sales Channels'),
                        'route' => [
                            'name' => 'grp.sales_channels.index'
                        ]
                    ]
                ]
            ]
        );
    }
}
