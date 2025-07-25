<?php

/*
 * author Arya Permana - Kirin
 * created on 11-04-2025-10h-58m
 * github: https://github.com/KirinZero0
 * copyright 2025
*/

namespace App\Actions\Dropshipping\Portfolio\UI;

use App\Actions\Dropshipping\CustomerSalesChannel\UI\ShowCustomerSalesChannel;
use App\Actions\Dropshipping\CustomerSalesChannel\UI\WithCustomerSalesChannelSubNavigation;
use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\WithCRMAuthorisation;
use App\Enums\Ordering\Platform\PlatformTypeEnum;
use App\Http\Resources\CRM\PortfoliosResource;
use App\InertiaTable\InertiaTable;
use App\Models\Catalogue\Shop;
use App\Models\CRM\Customer;
use App\Models\Dropshipping\CustomerSalesChannel;
use App\Models\Dropshipping\Portfolio;
use App\Models\SysAdmin\Organisation;
use App\Services\QueryBuilder;
use Closure;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;
use Spatie\QueryBuilder\AllowedFilter;

class IndexPortfoliosInCustomerSalesChannels extends OrgAction
{
    use WithCustomerSalesChannelSubNavigation;
    use WithCRMAuthorisation;

    private CustomerSalesChannel $customerSalesChannel;

    public function handle(CustomerSalesChannel $customerSalesChannel, $prefix = null): LengthAwarePaginator
    {
        $globalSearch = AllowedFilter::callback('global', function ($query, $value) {
            $query->where(function ($query) use ($value) {
                $query->whereStartWith('portfolios.reference', $value);
            });
        });

        if ($prefix) {
            InertiaTable::updateQueryBuilderParameters($prefix);
        }

        $query = QueryBuilder::for(Portfolio::class);
        $query->where('portfolios.customer_sales_channel_id', $customerSalesChannel->id);
        $query->leftJoin('customer_sales_channels', 'customer_sales_channels.id', 'portfolios.customer_sales_channel_id');

        $query->leftJoin('customers', 'customers.id', 'portfolios.customer_id');
        $query->leftJoin('platforms', 'platforms.id', 'portfolios.platform_id');


        return $query
            ->select([
                'portfolios.id',
                'portfolios.reference',
                'portfolios.created_at',
                'portfolios.item_name',
                'portfolios.item_code',
                'portfolios.item_type',
                'portfolios.platform_product_id',
                'portfolios.item_id',
                'portfolios.customer_sales_channel_id',
                'platform_possible_matches',
                'portfolios.exist_in_platform',
                'portfolios.platform_status',
                'portfolios.has_valid_platform_product_id',
                'customer_sales_channels.platform_status as customer_sales_channel_platform_status',
            ])
            ->defaultSort('portfolios.reference')
            ->allowedSorts(['reference', 'created_at'])
            ->allowedFilters([$globalSearch])
            ->withPaginator($prefix, tableName: request()->route()->getName())
            ->withQueryString();
    }

    public function htmlResponse(LengthAwarePaginator $portfolios, ActionRequest $request): Response
    {
        return Inertia::render(
            'Org/Dropshipping/Portfolios',
            [
                'breadcrumbs' => $this->getBreadcrumbs(
                    $this->customerSalesChannel,
                    $request->route()->getName(),
                    $request->route()->originalParameters()
                ),
                'title'       => __('Portfolio'),
                'pageHead'    => [
                    ...$this->getCustomerSalesChannelSubNavigationHead(
                        $this->customerSalesChannel,
                        $request,
                        __('Portfolio'),
                        [
                            'icon'  => ['fal', 'fa-bookmark'],
                            'title' => __('portfolios')
                        ]
                    ),
                    'actions' => [
                        [
                            'type' => 'button',
                            'style' => 'create',
                            'label' => __('Add Sales Channel'),
                            'route' => [
                                'name' => 'retina.dropshipping.customer_sales_channels.create',
                            ]
                        ],
                        [
                            'type' => 'button',
                            'style' => 'create',
                            'label' => __('Add Sales Channel'),
                            'route' => [
                                'name' => 'retina.dropshipping.customer_sales_channels.create',
                            ]
                        ]
                    ]

                ],

                'is_show_add_products_modal' => $this->customerSalesChannel->platform->type == PlatformTypeEnum::MANUAL,
                'data'                       => PortfoliosResource::collection($portfolios),
                'customer'                   => $this->customerSalesChannel->customer,
                'platform'                   => $this->customerSalesChannel->platform,
                'customerSalesChannel'       => $this->customerSalesChannel,
                'customerSalesChannelId'     => $this->customerSalesChannel->id,
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
                ->column(key: 'item_code', label: __('product'), canBeHidden: false, searchable: true)
                ->column(key: 'item_name', label: __('product name'), canBeHidden: false, searchable: true)
                ->column(key: 'platform_status', label: __('Status'), canBeHidden: false, sortable: true, searchable: true)
                ->column(key: 'matches', label: __('matches'), canBeHidden: false, sortable: true, searchable: true)
                ->column(key: 'actions', label: __('actions'), canBeHidden: false, sortable: true, searchable: true);


        };
    }

    public function asController(Organisation $organisation, Shop $shop, Customer $customer, CustomerSalesChannel $customerSalesChannel, ActionRequest $request): LengthAwarePaginator
    {
        $this->customerSalesChannel = $customerSalesChannel;
        $this->initialisationFromShop($shop, $request);

        return $this->handle($customerSalesChannel);
    }

    public function getBreadcrumbs(CustomerSalesChannel $customerSalesChannel, string $routeName, array $routeParameters): array
    {
        return array_merge(
            ShowCustomerSalesChannel::make()->getBreadcrumbs($customerSalesChannel, $routeName, $routeParameters),
            [
                [
                    'type'   => 'simple',
                    'simple' => [
                        'route' => [
                            'name'       => 'grp.org.shops.show.crm.customers.show.customer_sales_channels.show.portfolios.index',
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
