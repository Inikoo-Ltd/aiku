<?php

/*
 * Author: Artha <artha@aw-advantage.com>
 * Created: Wed, 15 Oct 2025 16:46:12 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Dropshipping\Portfolio\Logs;

use App\Actions\Dropshipping\CustomerSalesChannel\UI\ShowCustomerSalesChannel;
use App\Actions\Dropshipping\CustomerSalesChannel\UI\WithCustomerSalesChannelSubNavigation;
use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\WithCRMAuthorisation;
use App\Http\Resources\Dropshipping\PlatformPortfolioLogsResource;
use App\InertiaTable\InertiaTable;
use App\Models\Catalogue\Shop;
use App\Models\CRM\Customer;
use App\Models\Dropshipping\CustomerSalesChannel;
use App\Models\PlatformPortfolioLogs;
use App\Models\SysAdmin\Organisation;
use App\Services\QueryBuilder;
use Closure;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;
use Spatie\QueryBuilder\AllowedFilter;

class IndexPlatformPortfolioLogs extends OrgAction
{
    use WithCustomerSalesChannelSubNavigation;
    use WithCRMAuthorisation;

    private CustomerSalesChannel $customerSalesChannel;

    public function handle(CustomerSalesChannel $customerSalesChannel, $prefix = null): LengthAwarePaginator
    {
        $globalSearch = AllowedFilter::callback('global', function ($query, $value) {
            $query->where(function ($query) use ($value) {
                $query->whereWith('portfolios.item_code', $value)
                    ->orWhereWith('platform_portfolio_logs.type', $value)
                    ->orWhereWith('platform_portfolio_logs.status', $value);
            });
        });

        if ($prefix) {
            InertiaTable::updateQueryBuilderParameters($prefix);
        }

        $query = QueryBuilder::for(PlatformPortfolioLogs::class);
        $query->where('platform_portfolio_logs.customer_sales_channel_id', $customerSalesChannel->id);

        $query->leftJoin('portfolios', 'portfolios.id', 'platform_portfolio_logs.portfolio_id');
        $query->leftJoin('platforms', 'platforms.id', 'platform_portfolio_logs.platform_id');

        return $query
            ->select([
                'platform_portfolio_logs.id',
                'platform_portfolio_logs.created_at',
                'platform_portfolio_logs.type',
                'platform_portfolio_logs.status',
                'platform_portfolio_logs.response',
                'platform_portfolio_logs.platform_id',
                'platform_portfolio_logs.platform_type',
                'platform_portfolio_logs.portfolio_id',
                'portfolios.item_code',
                'platforms.name as platform_name',
            ])
            ->defaultSort('-platform_portfolio_logs.created_at')
            ->allowedSorts(['created_at', 'type', 'status', 'item_code'])
            ->allowedFilters([$globalSearch])
            ->withPaginator($prefix, tableName: request()->route()->getName())
            ->withQueryString();
    }

    public function htmlResponse(LengthAwarePaginator $platformPortfolioLogs, ActionRequest $request): Response
    {
        return Inertia::render(
            'Org/Dropshipping/PlatformPortfolioLogs',
            [
                'breadcrumbs' => $this->getBreadcrumbs(
                    $this->customerSalesChannel,
                    $request->route()->getName(),
                    $request->route()->originalParameters()
                ),
                'title'       => __('Platform Portfolio Logs'),
                'pageHead'    => [
                    ...$this->getCustomerSalesChannelSubNavigationHead(
                        $this->customerSalesChannel,
                        __('Platform Portfolio Logs'),
                        [
                            'icon'  => ['fal', 'fa-clipboard-list'],
                            'title' => __('Platform Portfolio Logs')
                        ]
                    )
                ],
                'data'                   => PlatformPortfolioLogsResource::collection($platformPortfolioLogs),
                'customer'               => $this->customerSalesChannel->customer,
                'platform'               => $this->customerSalesChannel->platform,
                'customerSalesChannel'   => $this->customerSalesChannel,
                'customerSalesChannelId' => $this->customerSalesChannel->id,
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
                ->column(key: 'item_code', label: __('Product Code'), canBeHidden: false, sortable: true, searchable: true)
                ->column(key: 'type', label: __('Type'), canBeHidden: false, sortable: true, searchable: true)
                ->column(key: 'platform_name', label: __('Platform'), canBeHidden: false, sortable: false, searchable: true)
                ->column(key: 'status', label: __('Status'), canBeHidden: false, sortable: true, searchable: true)
                ->column(key: 'response', label: __('Response'), canBeHidden: false, sortable: true, searchable: true)
                ->column(key: 'created_at', label: __('Date'), canBeHidden: false, sortable: true, searchable: false);
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
                            'name'       => 'grp.org.shops.show.crm.customers.show.customer_sales_channels.show.platform_portfolio_logs.index',
                            'parameters' => $routeParameters,
                        ],
                        'label' => __('Platform Portfolio Logs'),
                        'icon'  => 'fal fa-clipboard-list',
                    ],
                ]
            ]
        );
    }
}
