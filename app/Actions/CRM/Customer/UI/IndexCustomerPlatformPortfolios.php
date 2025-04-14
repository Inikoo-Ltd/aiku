<?php

/*
 * author Arya Permana - Kirin
 * created on 11-04-2025-10h-58m
 * github: https://github.com/KirinZero0
 * copyright 2025
*/

namespace App\Actions\CRM\Customer\UI;

use App\Actions\OrgAction;
use App\Enums\Catalogue\Portfolio\PortfolioTypeEnum;
use App\Enums\Ordering\Platform\PlatformTypeEnum;
use App\Http\Resources\CRM\PortfolioResource;
use App\InertiaTable\InertiaTable;
use App\Models\Catalogue\Product;
use App\Models\Catalogue\Shop;
use App\Models\CRM\Customer;
use App\Models\Dropshipping\Portfolio;
use App\Models\Ordering\ModelHasPlatform;
use App\Models\SysAdmin\Organisation;
use App\Services\QueryBuilder;
use Closure;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;
use Spatie\QueryBuilder\AllowedFilter;

class IndexCustomerPlatformPortfolios extends OrgAction
{
    use WithCustomerPlatformSubNavigation;

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

        $query = QueryBuilder::for(Portfolio::class);

        if ($modelHasPlatform->platform->type == PlatformTypeEnum::AIKU) {
            $query->where('portfolios.customer_id', $modelHasPlatform->model->id);
            $query->where('portfolios.type', PortfolioTypeEnum::MANUAL);
        } elseif ($modelHasPlatform->platform->type == PlatformTypeEnum::SHOPIFY) {
            $query->where('portfolios.customer_id', $modelHasPlatform->model->id);
            $query->where('portfolios.type', PortfolioTypeEnum::SHOPIFY);
        } elseif ($modelHasPlatform->platform->type == PlatformTypeEnum::TIKTOK) {
            $query->where('portfolios.customer_id', $modelHasPlatform->model->id);
            $query->where('portfolios.type', PortfolioTypeEnum::TIKTOK);
        }

        $query->where('item_type', class_basename(Product::class));

        return $query
        ->defaultSort('portfolios.reference')
        ->with('item')
        ->allowedSorts(['reference', 'created_at'])
        ->allowedFilters([$globalSearch])
        ->withPaginator($prefix, tableName: request()->route()->getName())
        ->withQueryString();
    }

    public function htmlResponse(LengthAwarePaginator $portfolios, ActionRequest $request): Response
    {
        $subNavigation = $this->getCustomerPlatformSubNavigation($this->modelHasPlatform, $this->modelHasPlatform->model, $request);
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
            'Org/Shop/CRM/Portfolios',
            [
                'breadcrumbs' => $this->getBreadcrumbs(
                    $this->modelHasPlatform,
                    $request->route()->getName(),
                    $request->route()->originalParameters()
                ),
                'title'       => __('Portfolios'),
                'pageHead'    => [
                    'title'         => $title,
                    'afterTitle'    => $afterTitle,
                    'iconRight'     => $iconRight,
                    'icon'          => $icon,
                    'subNavigation' => $subNavigation,
                ],
                'data'        => PortfolioResource::collection($portfolios),
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
                ->column(key: 'product_code', label: __('product'), canBeHidden: false, searchable: true)
                ->column(key: 'product_name', label: __('product name'), canBeHidden: false, searchable: true)
                ->column(key: 'reference', label: __('customer reference'), canBeHidden: false, sortable: true, searchable: true)
                ->column(key: 'type', label: __('type'), canBeHidden: false, sortable: true, searchable: true)
                ->column(key: 'created_at', label: __('created at'), canBeHidden: false, sortable: true, searchable: true);
        };
    }

    public function asController(Organisation $organisation, Shop $shop, Customer $customer, ModelHasPlatform $modelHasPlatform, ActionRequest $request): LengthAwarePaginator
    {
        $this->modelHasPlatform = $modelHasPlatform;
        $this->initialisationFromShop($shop, $request);

        return $this->handle($modelHasPlatform);
    }

    public function getBreadcrumbs(ModelHasPlatform $modelHasPlatform, string $routeName, array $routeParameters): array
    {
        return array_merge(
            ShowCustomerPlatform::make()->getBreadcrumbs($modelHasPlatform, $routeName, $routeParameters),
            [
                [
                    'type'   => 'simple',
                    'simple' => [
                        'route' => [
                            'name'       => 'grp.org.shops.show.crm.customers.show.platforms.show.portfolios.index',
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
