<?php

/*
 * Author: Jonathan Lopez Sanchez <jonathan@ancientwisdom.biz>
 * Created: Thu, 16 Mar 2023 08:17:51 Central European Standard Time, Malaga, Spain
 * Copyright (c) 2023, Inikoo LTD
 */

namespace App\Actions\Accounting\Payment\UI;

use App\Actions\Accounting\PaymentAccount\WithPaymentAccountSubNavigation;
use App\Actions\Comms\Traits\WithAccountingSubNavigation;
use App\Actions\OrgAction;
use App\Enums\Accounting\Payment\PaymentTypeEnum;
use App\Http\Resources\Accounting\PaymentsResource;
use App\InertiaTable\InertiaTable;
use App\Models\Accounting\Invoice;
use App\Models\Accounting\OrgPaymentServiceProvider;
use App\Models\Accounting\Payment;
use App\Models\Accounting\PaymentAccount;
use App\Models\Catalogue\Shop;
use App\Models\Fulfilment\Fulfilment;
use App\Models\SysAdmin\Group;
use App\Models\SysAdmin\Organisation;
use App\Services\QueryBuilder;
use Closure;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Lorisleiva\Actions\ActionRequest;
use Spatie\QueryBuilder\AllowedFilter;

class IndexRefundPayments extends OrgAction
{
    use WithPaymentAccountSubNavigation;
    use WithAccountingSubNavigation;

    private Fulfilment|Group|Organisation|PaymentAccount|Shop|OrgPaymentServiceProvider|Invoice $parent;

    public function handle(Payment $parent, $prefix = null): LengthAwarePaginator
    {
        $globalSearch = AllowedFilter::callback('global', function ($query, $value) {
            $query->where(function ($query) use ($value) {
                $query->whereStartWith('payment.reference', $value);
            });
        });

        if ($prefix) {
            InertiaTable::updateQueryBuilderParameters($prefix);
        }


        $queryBuilder = QueryBuilder::for(Payment::class);

        $queryBuilder->where('payments.type', PaymentTypeEnum::REFUND);
        $queryBuilder->where('payments.original_payment_id', $parent->id);

        $queryBuilder->leftjoin('organisations', 'payments.organisation_id', '=', 'organisations.id');
        $queryBuilder->leftjoin('shops', 'payments.shop_id', '=', 'shops.id');
        $queryBuilder->leftJoin('currencies', 'payments.currency_id', 'currencies.id');

        return $queryBuilder
            ->defaultSort('-date')
            ->select([
                'payments.id',
                'payments.reference',
                'payments.type',
                'payments.status',
                'payments.date',
                'payments.amount',
                'payment_accounts.name as payment_account_name',
                'payment_accounts.slug as payment_accounts_slug',
                'payment_service_providers.slug as payment_service_providers_slug',
                'shops.name as shop_name',
                'shops.slug as shop_slug',
                'organisations.name as organisation_name',
                'organisations.slug as organisation_slug',
                'currencies.code as currency_code',
            ])
            ->leftJoin('payment_accounts', 'payments.payment_account_id', 'payment_accounts.id')
            ->leftJoin('payment_service_providers', 'payment_accounts.payment_service_provider_id', 'payment_service_providers.id')
            ->allowedSorts(['reference', 'status', 'type' ,'date', 'amount', 'payment_account_name'])
            ->withBetweenDates(['date'])
            ->allowedFilters([$globalSearch])
            ->withPaginator($prefix, tableName: request()->route()->getName())
            ->withQueryString();
    }

    public function tableStructure(Payment $parent, ?array $modelOperations = null, $prefix = null): Closure
    {
        return function (InertiaTable $table) use ($modelOperations, $prefix, $parent) {
            if ($prefix) {
                $table
                    ->name($prefix)
                    ->pageName($prefix.'Page');
            }
            $table->betweenDates(['date']);

            $table
                ->withGlobalSearch()
                ->withModelOperations($modelOperations)
                ->withEmptyState([
                    'title' => __("No services found"),
                    'count' => $parent->refunds->count()
                ])
                ->defaultSort('-date')
                ->column(key: 'status', label: __('status'), canBeHidden: false, sortable: true, searchable: true, type: 'icon')
                ->column(key: 'reference', label: __('reference'), canBeHidden: false, sortable: true, searchable: true)
                ->column(key: 'payment_account_name', label: __('Payment Account'), canBeHidden: false, sortable: true, searchable: true)
                ->column(key: 'type', label: __('Type'), canBeHidden: false, sortable: true, searchable: true);

            $table->column(key: 'amount', label: __('amount'), canBeHidden: false, sortable: true, searchable: true, type:'number');
            $table->column(key: 'date', label: __('date'), canBeHidden: false, sortable: true, searchable: true, type:'number');
        };
    }

    public function authorize(ActionRequest $request): bool
    {
        if ($this->parent instanceof Group) {
            return $request->user()->authTo("group-overview");
        }
        $this->canEdit = $request->user()->authTo("accounting.{$this->organisation->id}.edit");

        return $request->user()->authTo("accounting.{$this->organisation->id}.view");
    }

    public function jsonResponse($payments): AnonymousResourceCollection
    {
        return PaymentsResource::collection($payments);
    }
}
