<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 18 Sep 2026 22:30:00 Central European Summer Time, Mijas, Spain
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Dispatching\ProductionOutput;

use App\InertiaTable\InertiaTable;
use App\Models\Production\JobOrderItem;
use App\Services\QueryBuilder;
use Closure;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Lorisleiva\Actions\Concerns\AsAction;
use Spatie\QueryBuilder\AllowedFilter;

class IndexProductionOutputItems
{
    use AsAction;

    /**
     * The finished trips flattened to one row per job and artefact, so the usual table can sort, search and page them.
     * A job carried to another day is one row, under one number, with the amount the whole job asks for.
     * What is still owed and where it goes is worked out by the trips, never by this query.
     *
     * @param  array<int, array<string, mixed>>  $trips  as returned by GetFinishedProductionJobOrders
     */
    public function handle(array $trips, string $prefix): LengthAwarePaginator
    {
        $destinationsByItem = [];
        $jobByItem          = [];
        foreach ($trips as $trip) {
            foreach ($trip['jobs'] as $job) {
                foreach ($job['items'] as $item) {
                    $destinationsByItem[$item['item_id']][] = [
                        'type'          => $trip['destination']['type'],
                        'label'         => $trip['destination']['label'],
                        'quantity'      => $item['quantity'],
                        'location_code' => $trip['destination']['location_code'] ?? $item['location_code'],
                        'locations'     => $item['locations'],
                        'item_ids'      => $item['item_ids'],
                        'job_order_ids' => $item['job_order_ids'],
                    ];
                    $jobByItem[$item['item_id']] = $item;
                }
            }
        }

        $globalSearch = AllowedFilter::callback('global', function ($query, $value) {
            $query->where(function ($query) use ($value) {
                $query->whereStartWith('artefacts.code', $value)
                    ->orWhereAnyWordStartWith('artefacts.name', $value)
                    ->orWhereStartWith('job_orders.reference', $value);
            });
        });

        InertiaTable::updateQueryBuilderParameters($prefix);

        return QueryBuilder::for(JobOrderItem::class)
            ->join('job_orders', 'job_orders.id', '=', 'job_order_items.job_order_id')
            ->join('artefacts', 'artefacts.id', '=', 'job_order_items.artefact_id')
            ->leftJoin('employees', 'employees.id', '=', 'job_orders.employee_id')
            ->whereIn('job_order_items.id', array_keys($destinationsByItem))
            ->select([
                'job_order_items.id',
                'job_order_items.job_order_id',
                'job_orders.reference as job_order_reference',
                'employees.contact_name as artisan',
                'artefacts.code',
                'artefacts.name',
            ])
            ->defaultSort('job_order_reference')
            ->allowedSorts(['job_order_reference', 'artisan', 'code', 'name'])
            ->allowedFilters([$globalSearch])
            ->withPaginator($prefix, tableName: request()->route()?->getName())
            ->withQueryString()
            ->through(fn (JobOrderItem $item) => [
                'id'                  => $item->id,
                'job_order_id'        => $item->job_order_id,
                'job_order_reference' => $jobByItem[$item->id]['reference'],
                'artisan'             => $item->artisan,
                'code'                => $item->code,
                'name'                => $item->name,
                'quantity_made'       => $jobByItem[$item->id]['quantity_made'],
                'quantity_total'      => $jobByItem[$item->id]['quantity_total'],
                'in_progress'         => $jobByItem[$item->id]['in_progress'],
                'destinations'        => $destinationsByItem[$item->id],
            ]);
    }

    public function tableStructure(string $prefix): Closure
    {
        return function (InertiaTable $table) use ($prefix) {
            $table->name($prefix)->pageName($prefix.'Page');
            $table->withGlobalSearch();
            $table->withEmptyState([
                'icons' => ['fal fa-industry'],
                'title' => __('Nothing finished waiting for the warehouse'),
            ]);

            $table->column(key: 'job_order_reference', label: __('Job order'), canBeHidden: false, sortable: true);
            $table->column(key: 'artisan', label: __('Artisan'), canBeHidden: false, sortable: true);
            $table->column(key: 'code', label: __('Code'), canBeHidden: false, sortable: true);
            $table->column(key: 'name', label: __('Name'), canBeHidden: false, sortable: true);
            $table->column(key: 'actions', label: __('To do actions'), canBeHidden: false);
            $table->defaultSort('job_order_reference');
        };
    }
}
