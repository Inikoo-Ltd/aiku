<?php

/*
 * Author: Ganes <gustiganes@gmail.com>
 * Created on: 09-05-2025, Bali, Indonesia
 * Github: https://github.com/Ganes556
 * Copyright: 2025
 *
*/

namespace App\Actions\Retina\Dropshipping\ApiToken\UI;

use App\Actions\Helpers\History\UI\IndexHistory;
use App\Actions\RetinaAction;
use App\Enums\UI\SysAdmin\ApiTokenRetinaTabsEnum;
use App\Http\Resources\Api\ApiTokensRetinaResource;
use App\Http\Resources\History\HistoryResource;
use App\InertiaTable\InertiaTable;
use App\Models\CRM\Customer;
use App\Models\Dropshipping\CustomerSalesChannel;
use Closure;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;
use Spatie\QueryBuilder\AllowedFilter;
use App\Services\QueryBuilder;
use Laravel\Sanctum\PersonalAccessToken;

class ShowRetinaApiDropshippingDashboard extends RetinaAction
{
    use AsAction;
    private CustomerSalesChannel $customerSalesChannel;

    public function handle(CustomerSalesChannel $customerSalesChannel, $prefix = null): LengthAwarePaginator
    {
        if ($prefix) {
            InertiaTable::updateQueryBuilderParameters($prefix);
        }

        $globalSearch = AllowedFilter::callback('global', function ($query, $value) {
            $query->where(function ($query) use ($value) {
                $query->whereAnyWordStartWith('name', $value);
            });
        });

        $queryBuilder = QueryBuilder::for(PersonalAccessToken::class);
        $queryBuilder->where('tokenable_type', class_basename($customerSalesChannel))
        ->where('tokenable_id', $customerSalesChannel->id);

        return $queryBuilder
            ->defaultSort('-created_at')
            ->select(['id', 'name',  'last_used_at', 'created_at', 'expires_at'])
            ->allowedSorts(['name', 'created_at', 'last_used_at'])
            ->allowedFilters([$globalSearch])
            ->withPaginator($prefix, tableName: request()->route()->getName())
            ->withQueryString();
    }

    public function htmlResponse(LengthAwarePaginator $apiTokens, ActionRequest $request): Response
    {
        return Inertia::render(
            'Dropshipping/Api/RetinaApiDropshippingDashboard',
            [
                'title'       => __('Api Token'),
                'pageHead'    => [
                    'title'     => 'API Token',
                    'icon'      => 'fal fa-key',
                    'noCapitalise'  => true,
                    // 'actions'   => [
                    //     [
                    //         'type'  => 'button',
                    //         'style' => 'edit',
                    //         'route' => [
                    //             'name'       => preg_replace('/show$/', 'edit', $request->route()->getName()),
                    //             'parameters' => $request->route()->originalParameters()
                    //         ]
                    //     ],

                    // ],

                ],
                'breadcrumbs' => $this->getBreadcrumbs(
                    __('Api Token')
                ),
                'tabs'                        => [
                    'current'    => $this->tab,
                    'navigation' => ApiTokenRetinaTabsEnum::navigation()
                ],
                'routes'                   => [
                    'create_token' => [
                        'name'       => 'retina.models.customer_sales_channel.access_token.create',
                        'parameters' => [
                            'customerSalesChannel' => $this->customerSalesChannel->id,
                        ]
                    ],
                ],
                'is_need_to_add_card' => true,  // TODO: Artha add condition
                // 'data'       => [
                //     // 'route_generate' => [
                //     //     'name' => 'retina.dropshipping.customer_sales_channels.api.show.token',
                //     //     'parameters' => [
                //     //         'customerSalesChannel' => $customerSalesChannel->slug,
                //     //     ],
                //     // ],
                //     // 'route_documentation' => '#',
                //     // 'route_show' => [
                //     //     'name' => 'retina.dropshipping.customer_sales_channels.api.show',
                //     //     'parameters' => [
                //     //         'customerSalesChannel' => $customerSalesChannel->slug,
                //     //     ],
                //     // ],

                // ],
                ApiTokenRetinaTabsEnum::API_TOKENS->value => $this->tab == ApiTokenRetinaTabsEnum::API_TOKENS->value ?
                    fn () => ApiTokensRetinaResource::collection($apiTokens)
                    : Inertia::lazy(fn () => ApiTokensRetinaResource::collection($apiTokens)),
                ApiTokenRetinaTabsEnum::HISTORY->value => $this->tab == ApiTokenRetinaTabsEnum::HISTORY->value ?
                fn () => HistoryResource::collection(IndexHistory::run($this->customer, prefix: ApiTokenRetinaTabsEnum::HISTORY->value))
                : Inertia::lazy(fn () => HistoryResource::collection(IndexHistory::run($this->customer, prefix: ApiTokenRetinaTabsEnum::HISTORY->value))),
            ]
        )
        ->table(IndexHistory::make()->tableStructure(prefix: ApiTokenRetinaTabsEnum::HISTORY->value))
        ->table($this->tableStructure(prefix: ApiTokenRetinaTabsEnum::API_TOKENS->value));
    }

    public function asController(CustomerSalesChannel $customerSalesChannel, ActionRequest $request): LengthAwarePaginator
    {
        $this->customerSalesChannel = $customerSalesChannel;
        $this->initialisation($request)->withTab(ApiTokenRetinaTabsEnum::values());
        return $this->handle($customerSalesChannel, ApiTokenRetinaTabsEnum::API_TOKENS->value);
    }

    public function getBreadcrumbs($label = null): array
    {
        return [
            [
                'type'   => 'simple',
                'simple' => [
                    'icon'  => 'fal fa-home',
                    'route' => [
                        'name' => 'retina.dashboard.show'
                    ]
                ]
            ],
        ];
    }

    public function tableStructure($prefix = null, array $modelOperations = []): Closure
    {
        return function (InertiaTable $table) use ($prefix, $modelOperations) {
            if ($prefix) {
                $table
                    ->name($prefix)
                    ->pageName($prefix.'Page');
            }
            $table
                ->withGlobalSearch()
                ->withModelOperations($modelOperations)
                ->column(key: 'name', label: __('Token ID'), canBeHidden: false, sortable: true, searchable: true)
                ->column(key: 'created_at', label: __('Created At'), canBeHidden: false, sortable: true, type: 'date_hms')
                ->column(key: 'last_used_at', label: __('Last Used'), canBeHidden: false, sortable: true, type: 'date_hms')
                ->column(key: 'expires_at', label: __('Expires At'), sortable: true, type: 'date_hms')
                ->column(key: 'actions', label: __('Actions'))
                ->defaultSort('-created_at');
        };
    }
}
