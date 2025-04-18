<?php

/*
 * author Arya Permana - Kirin
 * created on 02-04-2025-14h-10m
 * github: https://github.com/KirinZero0
 * copyright 2025
*/

namespace App\Actions\Fulfilment\FulfilmentCustomer\UI;

use App\Actions\Fulfilment\FulfilmentCustomer\ShowFulfilmentCustomer;
use App\Actions\Fulfilment\WithFulfilmentCustomerSubNavigation;
use App\Actions\OrgAction;
use App\Enums\Ordering\Platform\PlatformTypeEnum;
use App\Http\Resources\Fulfilment\FulfilmentCustomerPlatformsResource;
use App\Http\Resources\Platform\PlatformsResource;
use App\InertiaTable\InertiaTable;
use App\Models\Dropshipping\Platform;
use App\Models\Fulfilment\Fulfilment;
use App\Models\Fulfilment\FulfilmentCustomer;
use App\Models\SysAdmin\Organisation;
use App\Services\QueryBuilder;
use Closure;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;
use Spatie\QueryBuilder\AllowedFilter;

class IndexFulfilmentCustomerPlatforms extends OrgAction
{
    use WithFulfilmentCustomerSubNavigation;

    private FulfilmentCustomer $parent;

    public function handle(FulfilmentCustomer $fulfilmentCustomer, $prefix = null): LengthAwarePaginator
    {
        $globalSearch = AllowedFilter::callback('global', function ($query, $value) {
            $query->where(function ($query) use ($value) {
                $query->whereStartWith('platforms.code', $value);
            });
        });

        if ($prefix) {
            InertiaTable::updateQueryBuilderParameters($prefix);
        }

        $query = QueryBuilder::for(Platform::class);
        $query->join('customer_has_platforms', 'customer_has_platforms.platform_id', 'platforms.id');
        $query->where('customer_has_platforms.customer_id', $fulfilmentCustomer->customer_id);

        return $query
            ->defaultSort('customer_has_platforms.id')
            ->select(['customer_has_platforms.id as customer_has_platform_id', 'platforms.id', 'platforms.code', 'platforms.name', 'platforms.type'])
            ->allowedSorts(['code', 'name', 'type'])
            ->allowedFilters([$globalSearch])
            ->withPaginator($prefix, tableName: request()->route()->getName())
            ->withQueryString();
    }

    public function htmlResponse(LengthAwarePaginator $platforms, ActionRequest $request): Response
    {
        $subNavigation = $this->getFulfilmentCustomerSubNavigation($this->parent, $request);
        $icon          = ['fal', 'fa-user'];
        $title         = $this->parent->customer->name;
        $iconRight     = [
            'icon'  => ['fal', 'fa-user-friends'],
            'title' => __('channels')
        ];
        $afterTitle    = [

            'label' => __('Channels')
        ];

        $enableAiku = !$this->parent->customer->platforms()->where('type', PlatformTypeEnum::AIKU)->first();

        return Inertia::render(
            'Org/Fulfilment/FulfilmentCustomerPlatforms',
            [
                'breadcrumbs' => $this->getBreadcrumbs(
                    $request->route()->originalParameters()
                ),
                'title'       => __('Channels'),
                'pageHead'    => [
                    'title'         => $title,
                    'afterTitle'    => $afterTitle,
                    'iconRight'     => $iconRight,
                    'icon'          => $icon,
                    'subNavigation' => $subNavigation,
                    'actions'       => [
                        [
                            'type'    => 'button',
                            'style'   => 'create',
                            'tooltip' => __('New Channel'),
                            'label'   => __('New Channel'),
                            'key'     => 'new-channel',
                        ],
                    ],

                ],
                'data'        => FulfilmentCustomerPlatformsResource::collection($platforms),
                'platforms'   => PlatformsResource::collection($this->parent->group->platforms),
                'enableAiku'  => $enableAiku,
                'attachRoute' => [
                    'name'       => 'grp.models.customer.platform.attach',
                    'parameters' => [
                        'customer' => $this->parent->customer_id,
                    ]
                ]
            ]
        )->table($this->tableStructure());
    }


    public function tableStructure(array $modelOperations = null, $prefix = null): Closure
    {
        return function (InertiaTable $table) use ($modelOperations, $prefix) {
            if ($prefix) {
                $table
                    ->name($prefix)
                    ->pageName($prefix.'Page');
            }
            $table
                ->withModelOperations($modelOperations)
                ->withGlobalSearch()
                ->column(key: 'code', label: __('Code'), canBeHidden: false, sortable: true, searchable: true)
                ->defaultSort('code');
        };
    }

    public function asController(Organisation $organisation, Fulfilment $fulfilment, FulfilmentCustomer $fulfilmentCustomer, ActionRequest $request): LengthAwarePaginator
    {
        $this->parent = $fulfilmentCustomer;
        $this->initialisationFromFulfilment($fulfilment, $request);

        return $this->handle($fulfilmentCustomer);
    }

    public function getBreadcrumbs(array $routeParameters): array
    {
        return array_merge(
            ShowFulfilmentCustomer::make()->getBreadcrumbs($routeParameters),
            [
                [
                    'type'   => 'simple',
                    'simple' => [
                        'route' => [
                            'name'       => 'grp.org.fulfilments.show.crm.customers.show.platforms.index',
                            'parameters' => $routeParameters,
                        ],
                        'label' => __('Channels'),
                        'icon'  => 'fal fa-bars',
                    ],

                ]
            ]
        );
    }
}
