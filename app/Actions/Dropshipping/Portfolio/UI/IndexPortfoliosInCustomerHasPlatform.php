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
use App\Http\Resources\CRM\PortfoliosResource;
use App\InertiaTable\InertiaTable;
use App\Models\Catalogue\Shop;
use App\Models\CRM\Customer;
use App\Models\Dropshipping\CustomerSalesChannel;
use App\Models\Dropshipping\Platform;
use App\Models\Dropshipping\Portfolio;
use App\Models\SysAdmin\Organisation;
use App\Services\QueryBuilder;
use Closure;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;
use Spatie\QueryBuilder\AllowedFilter;

class IndexPortfoliosInCustomerHasPlatform extends OrgAction
{
    use WithCustomerSalesChannelSubNavigation;
    use WithCRMAuthorisation;

    private CustomerSalesChannel $customerHasPlatform;

    public function handle(CustomerSalesChannel $customerHasPlatform, $prefix = null): LengthAwarePaginator
    {
        $globalSearch = AllowedFilter::callback('global', function ($query, $value) {
            $query->where(function ($query) use ($value) {
                $query->whereStartWith('portfolio.reference', $value);
            });
        });

        if ($prefix) {
            InertiaTable::updateQueryBuilderParameters($prefix);
        }

        $query = QueryBuilder::for(Portfolio::class);
        $query->where('portfolios.customer_id', $customerHasPlatform->customer_id);
        $query->where('portfolios.platform_id', $customerHasPlatform->platform_id);

        $query->leftJoin('customers', 'customers.id', 'portfolios.customer_id');
        $query->leftJoin('platforms', 'platforms.id', 'portfolios.platform_id');

        $query->leftJoin('customer_sales_channels', function ($join) use ($customerHasPlatform) {
            $join->on('customer_sales_channels.customer_id', '=', 'portfolios.customer_id')
                ->where('customer_sales_channels.platform_id', '=', $customerHasPlatform->platform_id);
        });

        return $query
            ->select([
                'portfolios.id',
                'portfolios.reference',
                'portfolios.created_at',
                'portfolios.item_name',
                'portfolios.item_code',
                'portfolios.item_type',
                'portfolios.item_id',
                'customer_sales_channels.id as customer_sales_channel_id',
            ])
            ->defaultSort('portfolios.reference')
            ->allowedSorts(['reference', 'created_at'])
            ->allowedFilters([$globalSearch])
            ->withPaginator($prefix, tableName: request()->route()->getName())
            ->withQueryString();
    }

    public function htmlResponse(LengthAwarePaginator $portfolios, ActionRequest $request): Response
    {
        $subNavigation = $this->getCustomerPlatformSubNavigation($this->customerHasPlatform, $request);
        $icon          = ['fal', 'fa-user'];
        $title         = $this->customerHasPlatform->customer->name.' ('.$this->customerHasPlatform->customer->reference.')';
        $iconRight     = [
            'icon'  => ['fal', 'fa-bookmark'],
            'title' => __('portfolios')
        ];
        $afterTitle    = [
            'label' => __('Portfolios').' @'.$this->customerHasPlatform->platform->name,
        ];

        return Inertia::render(
            'Org/Dropshipping/Portfolios',
            [
                'breadcrumbs' => $this->getBreadcrumbs(
                    $this->customerHasPlatform->platform,
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
                'data'        => PortfoliosResource::collection($portfolios),
                'customer'      => $this->customerHasPlatform->customer,
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
                ->column(key: 'reference', label: __('customer reference'), canBeHidden: false, sortable: true, searchable: true)
                ->column(key: 'item_type', label: __('type'), canBeHidden: false, sortable: true, searchable: true)
                ->column(key: 'created_at', label: __('created at'), canBeHidden: false, sortable: true, searchable: true)
                ->column(key: 'action', label: __(' '), canBeHidden: false);
        };
    }

    public function asController(Organisation $organisation, Shop $shop, Customer $customer, Platform $platform, ActionRequest $request): LengthAwarePaginator
    {
        $customerHasPlatform = CustomerSalesChannel::where('customer_id', $customer->id)->where('platform_id', $platform->id)->first();
        $this->customerHasPlatform = $customerHasPlatform;
        $this->initialisationFromShop($shop, $request);

        return $this->handle($customerHasPlatform);
    }

    public function getBreadcrumbs(Platform $platform, string $routeName, array $routeParameters): array
    {
        return array_merge(
            ShowCustomerSalesChannel::make()->getBreadcrumbs($platform, $routeName, $routeParameters),
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
