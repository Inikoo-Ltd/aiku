<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 07 May 2025 12:26:31 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Accounting\PaymentAccount\UI;

use App\InertiaTable\InertiaTable;
use App\Models\Accounting\OrgPaymentServiceProvider;
use App\Models\Accounting\PaymentAccount;
use App\Models\Catalogue\Shop;
use App\Models\SysAdmin\Group;
use App\Models\SysAdmin\Organisation;
use App\Services\QueryBuilder;
use Closure;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Spatie\QueryBuilder\AllowedFilter;

trait IsIndexPaymentAccounts
{
    public function handle(Group|Shop|Organisation|OrgPaymentServiceProvider $parent, $prefix = null): LengthAwarePaginator
    {
        $organisation = null;
        if ($parent instanceof Organisation) {
            $organisation = $parent;
        } elseif (!$parent instanceof Group) {
            $organisation = $parent->organisation;
        }

        $globalSearch = AllowedFilter::callback('global', function ($query, $value) {
            $query->where(function ($query) use ($value) {
                $query->whereStartWith('payment_accounts.code', $value)
                    ->orWhereAnyWordStartWith('payment_accounts.name', $value);
            });
        });

        if ($prefix) {
            InertiaTable::updateQueryBuilderParameters($prefix);
        }

        $queryBuilder = QueryBuilder::for(PaymentAccount::class);

        if ($parent instanceof Organisation) {
            $queryBuilder->where('payment_accounts.organisation_id', $parent->id);
        } elseif ($parent instanceof OrgPaymentServiceProvider) {
            $queryBuilder->where('org_payment_service_provider_id', $parent->id);
        } elseif ($parent instanceof Shop) {
            $queryBuilder->where('payment_account_shop.shop_id', $parent->id);
        } elseif ($parent instanceof Group) {
            $queryBuilder->where('payment_accounts.group_id', $parent->id);
        }


        $queryBuilder->leftjoin('organisations', 'payment_accounts.organisation_id', 'organisations.id');

        $queryBuilder
            ->defaultSort('payment_accounts.code')
            ->select([
                'payment_accounts.id as id',
                'payment_accounts.code as code',
                'payment_accounts.name',
                'payment_account_stats.number_payments',
                'payment_account_stats.number_pas',
                'payment_account_stats.number_customers',
                'payment_accounts.slug as slug',
                'payment_service_providers.slug as payment_service_provider_slug',
                'payment_service_providers.name as payment_service_provider_name',
                'payment_service_providers.code as payment_service_provider_code',
                'payment_account_stats.number_pas_state_active',
                'org_amount_successfully_paid'
            ]);
        if ($organisation) {
            $queryBuilder->addSelect(DB::raw("'{$organisation->currency->code}' AS org_currency_code"));
        }

        return $queryBuilder->leftJoin('payment_account_stats', 'payment_accounts.id', 'payment_account_stats.payment_account_id')
            ->leftJoin('payment_service_providers', 'payment_accounts.payment_service_provider_id', 'payment_service_providers.id')
            ->allowedSorts(['code', 'name', 'number_payments', 'payment_service_provider_code', 'number_pas_state_active', 'org_amount_successfully_paid'])
            ->allowedFilters([$globalSearch])
            ->withPaginator($prefix, tableName: request()->route()->getName())
            ->withQueryString();
    }

    public function tableStructure(Group|Shop|Organisation|OrgPaymentServiceProvider $parent, ?array $modelOperations = null, $prefix = null): Closure
    {
        return function (InertiaTable $table) use ($modelOperations, $prefix, $parent) {
            if ($prefix) {
                $table
                    ->name($prefix)
                    ->pageName($prefix.'Page');
            }

            $table
                ->withGlobalSearch()
                ->withModelOperations($modelOperations)
                ->column(key: 'code', label: __('code'), canBeHidden: false, sortable: true, searchable: true)
                ->column(key: 'name', label: __('name'), canBeHidden: false, sortable: true, searchable: true);
            if ($parent instanceof Group) {
                $table->column(key: 'organisation_name', label: __('organisation'), canBeHidden: false, searchable: true);
            }

            if (!$parent instanceof OrgPaymentServiceProvider) {
                $table->column(key: 'payment_service_provider_code', label: __('provider'), canBeHidden: false, sortable: true, searchable: true);
            }

            $table->column(key: 'number_pas_state_active', label: __('shops'), canBeHidden: false, sortable: true, searchable: true);

            $table->column(key: 'number_payments', label: __('payments'), canBeHidden: false, sortable: true, searchable: true);
            $table->column(key: 'number_customers', label: __('customers'), canBeHidden: false, sortable: true, searchable: true);
            $table->column(key: 'org_amount_successfully_paid', label: __('amount'), canBeHidden: false, sortable: true, searchable: true, type: 'number');

            $table->defaultSort('code');
        };
    }

    public function headCrumb(string $routeName, array $routeParameters = []): array
    {
        return [
            [
                'type'   => 'simple',
                'simple' => [
                    'route' => [
                        'name'       => $routeName,
                        'parameters' => $routeParameters
                    ],
                    'label' => __('Payment accounts'),
                    'icon'  => 'fal fa-bars',

                ],

            ],
        ];
    }

}
