<?php

/*
 * author Arya Permana - Kirin
 * created on 07-05-2025-14h-25m
 * github: https://github.com/KirinZero0
 * copyright 2025
*/

namespace App\Actions\Accounting\TopUp\UI;

use App\Actions\Catalogue\Shop\UI\ShowShop;
use App\Actions\Comms\Traits\WithAccountingSubNavigation;
use App\Actions\OrgAction;
use App\Http\Resources\Accounting\TopUpsResource;
use App\InertiaTable\InertiaTable;
use App\Models\Accounting\TopUp;
use App\Models\Catalogue\Shop;
use App\Models\SysAdmin\Organisation;
use App\Services\QueryBuilder;
use Closure;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;
use Spatie\QueryBuilder\AllowedFilter;

class IndexTopUps extends OrgAction
{
    use WithAccountingSubNavigation;

    private Shop $parent;

    public function handle(Shop $parent, $prefix = null): LengthAwarePaginator
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
        $query->where('shop_id', $parent->id);

        return $query->defaultSort('id')
            ->allowedSorts(['id'])
            ->allowedFilters([$globalSearch])
            ->withPaginator($prefix, tableName: request()->route()->getName())
            ->withQueryString();
    }

    public function asController(Organisation $organisation, Shop $shop, ActionRequest $request): LengthAwarePaginator
    {
        $this->parent = $shop;
        $this->initialisationFromShop($shop, $request);

        return $this->handle($shop);
    }

    public function htmlResponse(LengthAwarePaginator $topups, ActionRequest $request): Response
    {
        return Inertia::render(
            'Org/Accounting/TopUps',
            [
                'breadcrumbs' => $this->getBreadcrumbs(
                    $request->route()->getName(),
                    $request->route()->originalParameters()
                ),
                'title'       => __('Topups'),
                'pageHead'    => [
                    'subNavigation' => $this->getSubNavigationShop($this->parent),
                    'title' => __('Topups'),
                    'icon'  => 'fal fa-shopping-basket'
                ],

                'data' => TopUpsResource::collection($topups)
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
                ->withEmptyState(
                    [
                        'title' => __('no topups'),
                        'count' => 0,
                    ]
                )
                ->withModelOperations($modelOperations);


            $table->column(key: 'reference', label: __('reference'), canBeHidden: false, searchable: true);
            $table->column(key: 'amount', label: __('amount'), canBeHidden: false, searchable: true);
            $table->column(key: 'status', label: __('status'), canBeHidden: false, searchable: true);
        };
    }

    public function getBreadcrumbs(string $routeName, array $routeParameters): array
    {
        $headCrumb = function () use ($routeName, $routeParameters) {
            return [
                [
                    'type'   => 'simple',
                    'simple' => [
                        'route' => [
                            'name'       => $routeName,
                            'parameters' => $routeParameters
                        ],
                        'label' => __('Topups'),
                        'icon'  => 'fal fa-bars',

                    ],
                ],
            ];
        };

        return match ($routeName) {
            'grp.org.shops.show.dashboard.payments.accounting.top_ups.index' =>
            array_merge(
                (new ShowShop())->getBreadcrumbs($routeParameters),
                $headCrumb()
            ),
            default => []
        };
    }
}
