<?php

/*
 * author Arya Permana - Kirin
 * created on 23-12-2024-15h-05m
 * github: https://github.com/KirinZero0
 * copyright 2024
*/

namespace App\Actions\Retina\Dropshipping\Poll\UI;

use App\Actions\Retina\UI\Dashboard\ShowRetinaDashboard;
use App\Actions\RetinaAction;
use App\Actions\Traits\WithCustomersSubNavigation;
use App\Http\Resources\CRM\PollsResource;
use App\InertiaTable\InertiaTable;
use App\Models\Catalogue\Shop;
use App\Models\CRM\Customer;
use App\Models\CRM\Poll;
use App\Models\Dropshipping\CustomerSalesChannel;
use App\Services\QueryBuilder;
use Closure;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;
use Spatie\QueryBuilder\AllowedFilter;

class IndexRetinaPolls extends RetinaAction
{
    use WithCustomersSubNavigation;
    // use WithCRMAuthorisation;

    public function handle(Shop $shop, $prefix = null): LengthAwarePaginator
    {
        $globalSearch = AllowedFilter::callback('global', function ($query, $value) {
            $query->where(function ($query) use ($value) {
                $query->whereAnyWordStartWith('polls.name', $value)
                    ->orWhereAnyWordStartWith('polls.label', $value);
            });
        });
        if ($prefix) {
            InertiaTable::updateQueryBuilderParameters($prefix);
        }

        $queryBuilder = QueryBuilder::for(Poll::class);

        $queryBuilder->where('shop_id', $shop->id);
        // $queryBuilder->where('polls.customer_sales_channel', $customerSalesChannel->id);

        $queryBuilder->leftJoin('poll_stats', function ($join) {
            $join->on('polls.id', '=', 'poll_stats.poll_id');
        });

        // if ($parent instanceof Shop) {
        // }
        $totalCustomer = DB::table('shop_crm_stats')
            ->where('shop_id', $shop->id)
            ->value('number_customers') ?? 0;


        // $totalClient = DB::table('customer_sales_channels')
        //     ->where('customer_sales_channel_id', $customerSalesChannel->id)
        //     ->value('number_customer_clients') ?? 0;

        $queryBuilder
            ->defaultSort('polls.id')
            ->select([
                'polls.id',
                'polls.slug',
                'polls.name',
                'polls.label',
                'polls.position',
                'polls.type',
                'polls.in_registration',
                'poll_stats.*',
                DB::raw("'$totalCustomer' AS total_customers"),
            ])
            ->groupBy('polls.id', 'poll_stats.id');

        return $queryBuilder
            ->allowedSorts(['name', 'type', 'number_customer_clients', 'in_registration'])
            ->allowedFilters([$globalSearch])
            ->withPaginator($prefix, tableName: request()->route()->getName())
            ->withQueryString();
    }

    public function tableStructure(
        Customer $customer,
        ?array $modelOperations = null,
        $prefix = null,
    ): Closure {
        return function (InertiaTable $table) use ($customer, $modelOperations, $prefix) {
            if ($prefix) {
                $table->name($prefix)->pageName($prefix.'Page');
            }

            $table
                ->withGlobalSearch()
                ->withModelOperations($modelOperations)
                ->withEmptyState(
                    match (class_basename($customer)) {
                        'Organisation', 'Shop', 'Customer' => [
                            'title' => __("No polls found"),
                        ],
                        default => null
                    }
                );

            $table
                ->column(key: 'name', label: __('Name'), canBeHidden: false, sortable: true, searchable: true)
                ->column(key: 'label', label: __('Label'), canBeHidden: false, sortable: true, searchable: true)
                ->column(key: 'in_registration', label: __('In registration'), canBeHidden: false, sortable: true)
                ->column(key: 'type', label: __('Type'), canBeHidden: false, sortable: true, searchable: true)
                ->column(key: 'number_customers', label: __('Customers'), canBeHidden: false, sortable: true, searchable: true)
                ->column(key: 'percentage', label: __('Response %'), canBeHidden: false);
        };
    }

    public function jsonResponse(LengthAwarePaginator $polls): AnonymousResourceCollection
    {
        return PollsResource::collection($polls);
    }

    public function htmlResponse(LengthAwarePaginator $polls, ActionRequest $request): Response
    {
        $subNavigation = null;
        if ($this->parent instanceof Shop) {
            $subNavigation = $this->getSubNavigation($request);
        }
        $title      = __('Polls');
        $model      = __('Poll');
        $icon       = [
            'icon'  => ['fal', 'fa-cube'],
            'title' => __('polls')
        ];
        $iconRight  = [
            'icon' => 'fal fa-cube',
        ];
        $afterTitle = [
            'label' => __('Polls')
        ];

        $action = [];
        // $action = [
        //     [
        //         'type'    => 'button',
        //         'style'   => 'create',
        //         'tooltip' => __('New Poll'),
        //         'label'   => __('New Poll'),
        //         'route'   => [
        //             'name'       => 'grp.org.shops.show.crm.polls.create',
        //             'parameters' => $request->route()->originalParameters()
        //         ]
        //     ],
        // ];

        return Inertia::render(
            'Org/Shop/CRM/Polls',
            [
                'breadcrumbs' => $this->getBreadcrumbs(
                    $request->route()->getName(),
                    $request->route()->originalParameters()
                ),
                'title'       => __('Polls'),
                'pageHead'    => [
                    'title'         => $title,
                    'icon'          => $icon,
                    'model'         => $model,
                    'afterTitle'    => $afterTitle,
                    'iconRight'     => $iconRight,
                    'subNavigation' => $subNavigation,
                    'actions'       => $action,
                ],
                'data'        => PollsResource::collection($polls),
            ]
        )->table($this->tableStructure($this->customer));
    }

    public function asController(ActionRequest $request): LengthAwarePaginator
    {
        // $this->customerSalesChannel = $customerSalesChannel;
        $this->initialisation($request);
        return $this->handle($this->shop);
    }

    public function getBreadcrumbs(string $routeName, array $routeParameters): array
    {
        $headCrumb = function (array $routeParameters = []) {
            return [
                [
                    'type'   => 'simple',
                    'simple' => [
                        'route' => $routeParameters,
                        'label' => __('Polls'),
                        'icon'  => 'fal fa-bars'
                    ],
                ],
            ];
        };

        return match ($routeName) {
            'retina.dropshipping.polls.index' =>
            array_merge(
                ShowRetinaDashboard::make()->getBreadcrumbs(),
                $headCrumb(
                    [
                        'name'       => 'retina.dropshipping.polls.index',
                        'parameters' => []
                    ]
                )
            ),
            default => []
        };
    }
}
