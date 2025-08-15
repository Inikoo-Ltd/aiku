<?php

/*
 * Author: Jonathan Lopez Sanchez <jonathan@ancientwisdom.biz>
 * Created: Thu, 16 Mar 2023 15:40:54 Central European Standard Time, Malaga, Spain
 * Copyright (c) 2023, Inikoo LTD
 */

namespace App\Actions\Accounting\PaymentAccount\Json;

use App\Actions\OrgAction;
use App\Http\Resources\Accounting\PaymentAccountsResource;
use App\InertiaTable\InertiaTable;
use App\Models\Accounting\PaymentAccount;
use App\Models\Catalogue\Shop;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Lorisleiva\Actions\ActionRequest;
use Spatie\QueryBuilder\AllowedFilter;
use App\Services\QueryBuilder;

class GetShopPaymentAccounts extends OrgAction
{
    public function handle(Shop $shop, $prefix = null): LengthAwarePaginator
    {
        $globalSearch = AllowedFilter::callback('global', function ($query, $value) {
            $query->where(function ($query) use ($value) {
                $query->whereStartWith('payment_accounts.code', $value)
                    ->orWhereAnyWordStartWith('payment_accounts.name', 'ILIKE', $value);
            });
        });

        if ($prefix) {
            InertiaTable::updateQueryBuilderParameters($prefix);
        }

        $queryBuilder = QueryBuilder::for(PaymentAccount::class);
        $queryBuilder->where('payment_account_shop.shop_id', $shop->id);

        return $queryBuilder
            ->defaultSort('payment_accounts.code')
            ->select([
                'payment_accounts.id as id',
                'payment_accounts.code as code',
                'payment_accounts.name',
                'number_payments',
                'payment_accounts.slug as slug',
                'payment_service_providers.slug as payment_service_provider_slug',
                'payment_service_providers.name as payment_service_provider_name',
                'payment_service_providers.code as payment_service_provider_code',
                'shops.code as shop_code',
                'shops.name as shop_name',
                'shops.id as shop_id'
            ])
            ->leftJoin('payment_account_shop', 'payment_account_shop.payment_account_id', 'payment_accounts.id')
            ->leftJoin('shops', 'payment_account_shop.shop_id', 'shops.id')
            ->leftJoin('payment_account_stats', 'payment_accounts.id', 'payment_account_stats.payment_account_id')
            ->leftJoin('payment_service_providers', 'payment_service_provider_id', 'payment_service_providers.id')
            ->allowedSorts(['code', 'name', 'number_payments','payment_service_provider_code'])
            ->allowedFilters([$globalSearch])
            ->withPaginator($prefix)
            ->withQueryString();
    }

    // public function authorize(ActionRequest $request): bool //TODO: raul pls fix
    // {
    //     $this->canEdit = $request->user()->authTo("accounting.{$this->organisation->id}.edit");

    //     return $request->user()->authTo("accounting.{$this->organisation->id}.view");
    // }

    public function asController(Shop $shop, ActionRequest $request): LengthAwarePaginator
    {
        $this->initialisationFromShop($shop, $request);

        return $this->handle($shop);
    }

    public function jsonResponse(LengthAwarePaginator $paymentAccounts): AnonymousResourceCollection
    {
        return PaymentAccountsResource::collection($paymentAccounts);
    }
}
