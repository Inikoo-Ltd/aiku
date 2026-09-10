<?php

namespace App\Actions\CRM\Customer\Json;

use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\WithCatalogueAuthorisation;
use App\Http\Resources\CRM\CustomersForSelectResource;
use App\Models\Catalogue\Shop;
use App\Models\CRM\Customer;
use App\Services\QueryBuilder;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Lorisleiva\Actions\ActionRequest;
use Spatie\QueryBuilder\AllowedFilter;

class GetCustomersInShop extends OrgAction
{
    use WithCatalogueAuthorisation;

    private Shop $parent;

    public function handle(Shop $parent, $prefix = null): LengthAwarePaginator
    {
        $globalSearch = AllowedFilter::callback('global', function ($query, $value) {
            $digits = preg_replace('/\D/', '', (string) $value);

            $query->where(function ($query) use ($value, $digits) {
                $query->whereAnyWordStartWith('customers.name', $value)
                    ->orWhereStartWith('customers.reference', $value)
                    ->orWhereStartWith('customers.email', $value)
                    ->orWhereStartWith('customers.phone', $value);

                if ($digits !== '') {
                    $query->orWhereRaw(
                        "regexp_replace(customers.phone, '\\D', '', 'g') like ?",
                        [$digits.'%']
                    );
                }
            });
        });

        $hasPhoneFilter = AllowedFilter::callback('has_phone', function ($query, $value) {
            if (filter_var($value, FILTER_VALIDATE_BOOLEAN)) {
                $query->whereNotNull('customers.phone')
                    ->where('customers.phone', '!=', '');
            }
        });

        $phoneFilter = AllowedFilter::callback('phone', function ($query, $value) {
            $query->whereRaw(
                "regexp_replace(customers.phone, '\\D', '', 'g') = ?",
                [preg_replace('/\D/', '', (string) $value)]
            );
        });

        $queryBuilder = QueryBuilder::for(Customer::class);
        $queryBuilder->where('customers.shop_id', $parent->id);

        return $queryBuilder->defaultSort('-id')
            ->allowedFilters([$globalSearch, $hasPhoneFilter, $phoneFilter])
            ->withPaginator($prefix)
            ->withQueryString();
    }

    public function jsonResponse(LengthAwarePaginator $customers): AnonymousResourceCollection
    {
        return CustomersForSelectResource::collection($customers);
    }

    public function asController(Shop $shop, ActionRequest $request): LengthAwarePaginator
    {
        $this->parent = $shop;
        $this->initialisationFromShop($shop, $request);

        return $this->handle(parent: $shop);
    }
}
