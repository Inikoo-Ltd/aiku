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
    use WithCatalogueAuthorisation {
        authorize as catalogueAuthorize;
    }

    private Shop $parent;

    public function handle(Shop $parent, $prefix = null): LengthAwarePaginator
    {
        $globalSearch = AllowedFilter::callback('global', function ($query, $value) {
            $digits = preg_match('/[\p{L}@]/u', (string) $value) ? '' : preg_replace('/\D/', '', (string) $value);

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

            $search = trim((string) $value);
            $prefix = str_replace(['\\', '%', '_'], ['\\\\', '\%', '\_'], $search).'%';

            $query->reorder()
                ->orderByRaw(
                    'CASE
                        WHEN lower(customers.email) = lower(?) OR lower(customers.reference) = lower(?) THEN 0
                        WHEN customers.email COLLATE "C" ILIKE ? OR customers.reference COLLATE "C" ILIKE ? THEN 1
                        WHEN customers.name COLLATE "C" ILIKE ? OR customers.name COLLATE "C" ILIKE ? THEN 2
                        ELSE 3
                    END',
                    [$search, $search, $prefix, $prefix, $prefix, '% '.$prefix]
                )
                ->orderByDesc('customers.id');
        });

        $hasPhoneFilter = AllowedFilter::callback('has_phone', function ($query, $value) {
            if (filter_var($value, FILTER_VALIDATE_BOOLEAN)) {
                $query->whereNotNull('customers.phone')
                    ->where('customers.phone', '!=', '');
            }
        });

        $hasEmailFilter = AllowedFilter::callback('has_email', function ($query, $value) {
            if (filter_var($value, FILTER_VALIDATE_BOOLEAN)) {
                $query->whereNotNull('customers.email')
                    ->where('customers.email', '!=', '');
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
            ->allowedFilters([$globalSearch, $hasPhoneFilter, $hasEmailFilter, $phoneFilter])
            ->withPaginator($prefix)
            ->withQueryString();
    }

    public function authorize(ActionRequest $request): bool
    {
        return $this->catalogueAuthorize($request) || $request->user()->authTo("crm.{$this->shop->id}.view");
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
