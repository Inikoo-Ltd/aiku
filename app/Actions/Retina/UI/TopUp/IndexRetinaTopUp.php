<?php

/*
 * Author: Artha <artha@aw-advantage.com>
 * Created: Wed, 16 Oct 2024 10:47:26 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Retina\UI\TopUp;

use App\Actions\Retina\UI\Dashboard\ShowRetinaDashboard;
use App\Actions\RetinaAction;
use App\Http\Resources\Fulfilment\RetinaTopupResources;
use App\InertiaTable\InertiaTable;
use App\Models\Accounting\TopUp;
use App\Models\CRM\Customer;
use App\Services\QueryBuilder;
use Closure;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;
use Spatie\QueryBuilder\AllowedFilter;

class IndexRetinaTopUp extends RetinaAction
{
    public function handle(Customer $parent, $prefix = null): \Illuminate\Pagination\LengthAwarePaginator
    {
        $globalSearch = AllowedFilter::callback('global', function ($query, $value) {
            $query->where(function ($query) use ($value) {
                $query->whereAnyWordStartWith('reference', $value)
                    ->orWhereWith('reference', $value);
            });
        });

        if ($prefix) {
            InertiaTable::updateQueryBuilderParameters($prefix);
        }

        $query = QueryBuilder::for(TopUp::class);
        $query->where('customer_id', $parent->id);

        return $query->defaultSort('id')
            ->allowedSorts(['id'])
            ->allowedFilters([$globalSearch])
            ->withPaginator($prefix, tableName: request()->route()->getName())
            ->withQueryString();
    }

    public function authorize(ActionRequest $request): bool
    {
        return $request->user()->is_root;
    }

    public function asController(ActionRequest $request): LengthAwarePaginator
    {
        $this->initialisation($request);

        $customer = $request->user()->customer;

        return $this->handle($customer);
    }

    public function htmlResponse(LengthAwarePaginator $topups): Response
    {
        return Inertia::render(
            'Dropshipping/TopUp/TopUps',
            [
                'breadcrumbs' => $this->getBreadcrumbs(),
                'title'       => __('Topups'),
                'pageHead'    => [
                    'title' => __('Topups'),
                    'icon'  => 'fal fa-shopping-basket'
                ],

                'data' => RetinaTopupResources::collection($topups)
            ]
        )->table($this->tableStructure());
    }

    public function tableStructure($prefix = null, $modelOperations = []): Closure
    {
        return function (InertiaTable $table) use ($prefix, $modelOperations) {
            if ($prefix) {
                $table
                    ->name($prefix)
                    ->pageName($prefix.'Page');
            }

            $emptyStateData = [
                'icons' => ['fal fa-pallet'],
                'title' => __("No topup exist"),
                'count' => 0
            ];

            $table->withGlobalSearch()
                ->withEmptyState($emptyStateData)
                ->withModelOperations($modelOperations);


            $table->column(key: 'reference', label: __('reference'), canBeHidden: false, searchable: true);
            $table->column(key: 'amount', label: __('amount'), canBeHidden: false, searchable: true);
            $table->column(key: 'status', label: __('status'), canBeHidden: false, searchable: true);
        };
    }

    public function getBreadcrumbs(): array
    {
        return
            array_merge(
                ShowRetinaDashboard::make()->getBreadcrumbs(),
                [
                    [
                        'type'   => 'simple',
                        'simple' => [
                            'route' => [
                                'name' => 'retina.topup.index'
                            ],
                            'label'  => __('Topup'),
                        ]
                    ]
                ]
            );
    }
}
