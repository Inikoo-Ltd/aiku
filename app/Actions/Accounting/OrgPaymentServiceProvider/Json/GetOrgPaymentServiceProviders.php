<?php

/*
 * author Arya Permana - Kirin
 * created on 01-07-2025-16h-14m
 * github: https://github.com/KirinZero0
 * copyright 2025
*/

namespace App\Actions\Accounting\OrgPaymentServiceProvider\Json;

use App\Actions\OrgAction;
use App\Http\Resources\Accounting\OrgPaymentServiceProvidersResource;
use App\InertiaTable\InertiaTable;
use App\Models\Accounting\PaymentServiceProvider;
use App\Models\SysAdmin\Organisation;
use App\Services\QueryBuilder;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Lorisleiva\Actions\ActionRequest;
use Spatie\QueryBuilder\AllowedFilter;

class GetOrgPaymentServiceProviders extends OrgAction
{
    public function handle(Organisation $parent, $prefix = null): LengthAwarePaginator
    {
        $globalSearch = AllowedFilter::callback('global', function ($query, $value) {
            $query->where(function ($query) use ($value) {
                $query->whereStartWith('payment_service_providers.code', $value)
                    ->orWhereAnyWordStartWith('payment_service_providers.name', $value);
            });
        });

        if ($prefix) {
            InertiaTable::updateQueryBuilderParameters($prefix);
        }

        $queryBuilder = QueryBuilder::for(PaymentServiceProvider::class);

        $queryBuilder->where('payment_service_providers.group_id', $parent->group_id);


        return $queryBuilder
            ->defaultSort('payment_service_providers.code')
            ->select(['org_payment_service_providers.slug','payment_service_providers.code', 'payment_service_providers.state', 'org_payment_service_providers.code as org_code', 'org_payment_service_providers.slug as org_slug', 'org_payment_service_provider_stats.number_payment_accounts', 'org_payment_service_provider_stats.number_payments','name', 'payment_service_providers.id'])
            ->leftJoin(
                'org_payment_service_providers',
                function ($leftJoin) use ($parent) {
                    $leftJoin->on('payment_service_providers.id', '=', 'org_payment_service_providers.payment_service_provider_id')
                        ->where('org_payment_service_providers.organisation_id', '=', $parent->id)
                        ->leftJoin('org_payment_service_provider_stats', 'org_payment_service_providers.id', 'org_payment_service_provider_stats.org_payment_service_provider_id');


                }
            )

            ->with('media')
            ->allowedSorts(['code', 'number_payment_accounts', 'number_payments','name'])
            ->allowedFilters([$globalSearch])
            ->withPaginator($prefix)
            ->withQueryString();
    }

    public function jsonResponse(LengthAwarePaginator $paymentServiceProviders): AnonymousResourceCollection
    {
        return OrgPaymentServiceProvidersResource::collection($paymentServiceProviders);
    }


    public function asController(Organisation $organisation, ActionRequest $request): LengthAwarePaginator
    {
        $this->initialisation($organisation, $request);
        return $this->handle($organisation);
    }
}
