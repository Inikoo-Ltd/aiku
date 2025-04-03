<?php
/*
 * author Arya Permana - Kirin
 * created on 03-04-2025-11h-17m
 * github: https://github.com/KirinZero0
 * copyright 2025
*/

namespace App\Actions\Fulfilment\FulfilmentCustomer\UI;

use App\Actions\Fulfilment\FulfilmentCustomer\ShowFulfilmentCustomer;
use App\Actions\Fulfilment\WithFulfilmentCustomerPlatformSubNavigation;
use App\Actions\Fulfilment\WithFulfilmentCustomerSubNavigation;
use App\Actions\OrgAction;
use App\Enums\Ordering\Platform\PlatformTypeEnum;
use App\Http\Resources\Fulfilment\FulfilmentCustomerPlatformsResource;
use App\Http\Resources\Fulfilment\StoredItemResource;
use App\Http\Resources\Fulfilment\StoredItemsResource;
use App\Http\Resources\Platform\PlatformsResource;
use App\InertiaTable\InertiaTable;
use App\Models\Dropshipping\Platform;
use App\Models\Fulfilment\Fulfilment;
use App\Models\Fulfilment\FulfilmentCustomer;
use App\Models\Fulfilment\StoredItem;
use App\Models\Ordering\ModelHasPlatform;
use App\Models\SysAdmin\Organisation;
use App\Services\QueryBuilder;
use Closure;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;
use Spatie\QueryBuilder\AllowedFilter;

class IndexFulfilmentCustomerPlatformPortfolios extends OrgAction
{
    use WithFulfilmentCustomerPlatformSubNavigation;

    private ModelHasPlatform $modelHasPlatform;

    public function handle(ModelHasPlatform $modelHasPlatform, $prefix = null): LengthAwarePaginator
    {
        $globalSearch = AllowedFilter::callback('global', function ($query, $value) {
            $query->where(function ($query) use ($value) {
                $query->where('stored_items.reference', 'ILIKE', "$value%");
            });
        });

        if ($prefix) {
            InertiaTable::updateQueryBuilderParameters($prefix);
        }

        $query = QueryBuilder::for(StoredItem::class);

        if($modelHasPlatform->platform->type == PlatformTypeEnum::AIKU) {
            $query->where('stored_items.fulfilment_customer_id', $modelHasPlatform->model->fulfilmentCustomer->id);
        } elseif ($modelHasPlatform->platform->type == PlatformTypeEnum::SHOPIFY) {
            $query->leftJoin('shopify_user_has_products', function ($join) {
                $join->on('shopify_user_has_products.product_id', '=', 'stored_items.id')
                        ->where('shopify_user_has_products.product_type', '=', 'StoredItem');
            });
            $query->where('shopify_user_has_products.shopify_user_id', $modelHasPlatform->model->shopifyUser->id);
        } elseif ($modelHasPlatform->platform->type == PlatformTypeEnum::TIKTOK) {
            $query->leftJoin('shopify_user_has_products', function ($join) {
                $join->on('tiktok_user_has_products.product_id', '=', 'stored_items.id')
                        ->where('tiktok_user_has_products.product_type', '=', 'StoredItem');
            });
            $query->where('tiktok_user_has_products.shopify_user_id', $modelHasPlatform->model->tiktokUser->id);
        }

        return $query
            ->defaultSort('stored_items.id')
            ->select(
                    'stored_items.id',
                    'stored_items.slug',
                    'stored_items.reference',
                    'stored_items.state',
                    'stored_items.name',
                    'stored_items.total_quantity'
                    )
            ->allowedSorts(['reference', 'name', 'total_quantity'])
            ->allowedFilters([$globalSearch])
            ->withPaginator($prefix, tableName: request()->route()->getName())
            ->withQueryString();
    }

    public function htmlResponse(LengthAwarePaginator $portfolios, ActionRequest $request): Response
    {
        $subNavigation = $this->getFulfilmentCustomerPlatformSubNavigation($this->modelHasPlatform, $this->modelHasPlatform->model->fulfilmentCustomer, $request);
        $icon       = ['fal', 'fa-user'];
        $title      = $this->modelHasPlatform->model->name;
        $iconRight  = [
            'icon'  => ['fal', 'fa-user-friends'],
            'title' => __('portfolios')
        ];
        $afterTitle = [

            'label' => __('Portfolios')
        ];

        return Inertia::render(
            'Org/Fulfilment/FulfilmentCustomerPlatformPortfolios',
            [
                'breadcrumbs' => $this->getBreadcrumbs(
                    $this->modelHasPlatform,
                    $request->route()->originalParameters()
                ),
                'title'       => __('Channels'),
                'pageHead'    => [
                    'title'         => $title,
                    'afterTitle'    => $afterTitle,
                    'iconRight'     => $iconRight,
                    'icon'          => $icon,
                    'subNavigation' => $subNavigation,
                ],
                'data'        => StoredItemsResource::collection($portfolios),
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
                ->column(key: 'state', label: __('State'), canBeHidden: false, sortable: true, searchable: true)
                ->column(key: 'reference', label: __('Reference'), canBeHidden: false, sortable: true, searchable: true)
                ->column(key: 'name', label: __('Name'), canBeHidden: false, sortable: true, searchable: true)
                ->column(key: 'total_quantity', label: __('Quantity'), canBeHidden: false, sortable: true, searchable: true)
                ->defaultSort('reference');
        };
    }

    public function asController(Organisation $organisation, Fulfilment $fulfilment, FulfilmentCustomer $fulfilmentCustomer, ModelHasPlatform $modelHasPlatform, ActionRequest $request): LengthAwarePaginator
    {
        $this->modelHasPlatform = $modelHasPlatform;
        $this->initialisationFromFulfilment($fulfilment, $request);

        return $this->handle($modelHasPlatform);
    }

    public function getBreadcrumbs(ModelHasPlatform $modelHasPlatform, array $routeParameters): array
    {
        return array_merge(
            ShowFulfilmentCustomerPlatform::make()->getBreadcrumbs($modelHasPlatform, $routeParameters),
            [
                [
                    'type'   => 'simple',
                    'simple' => [
                        'route' => [
                            'name'       => 'grp.org.fulfilments.show.crm.customers.show.platforms.show.portfolios.index',
                            'parameters' => $routeParameters,
                        ],
                        'label' => __('Portfolios'),
                        'icon'  => 'fal fa-bars',
                    ],

                ]
            ]
        );
    }
}
