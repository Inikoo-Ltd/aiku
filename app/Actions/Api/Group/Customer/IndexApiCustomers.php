<?php

/*
 * author Arya Permana - Kirin
 * created on 23-06-2025-11h-47m
 * github: https://github.com/KirinZero0
 * copyright 2025
*/

namespace App\Actions\Api\Group\Customer;

use App\Actions\Api\Group\Resources\CustomersApiResource;
use App\Actions\OrgAction;
use App\Models\Catalogue\Shop;
use App\Models\CRM\Customer;
use App\Services\QueryBuilder;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Arr;
use Lorisleiva\Actions\ActionRequest;

class IndexApiCustomers extends OrgAction
{
    public function authorize(ActionRequest $request): bool
    {
        return $request->user()->authTo(
            [
                "crm.{$this->shop->id}.view",
                "accounting.{$this->shop->organisation_id}.view"
            ]
        );
    }

    public function handle(Shop $shop, array $modelData): LengthAwarePaginator
    {
        $query = QueryBuilder::for(Customer::class);

        $query->where('customers.shop_id', $shop->id)
            ->leftJoin('customer_stats', 'customers.id', 'customer_stats.customer_id');

        if (Arr::get($modelData, 'global')) {
            $this->getGlobalSearch($query, Arr::get($modelData, 'global'));
        } elseif (Arr::get($modelData, 'email')) {
            $this->getEmailSearch($query, Arr::get($modelData, 'email'));
        } elseif (Arr::get($modelData, 'name')) {
            $this->getNameSearch($query, Arr::get($modelData, 'name'));
        } elseif (Arr::get($modelData, 'reference')) {
            $this->getReferenceSearch($query, Arr::get($modelData, 'reference'));
        }

        return $query->defaultSort('customers.id')
        ->select([
                'customers.location',
                'customers.reference',
                'customers.id',
                'customers.name',
                'customers.contact_name',
                'customers.company_name',
                'customers.email',
                'customers.phone',
                'customers.slug',
                'customers.created_at',
                'customers.updated_at',
        ])
            ->allowedSorts(['name', 'contact_name', 'reference', 'created_at'])
            ->withBetweenDates(['created_at'])
            ->withPaginator(null, queryName: 'per_page')
            ->withQueryString();
    }

    public function getGlobalSearch($query, string $value): QueryBuilder
    {
        return $query->where(function ($query) use ($value) {
            $query->whereAnyWordStartWith('customers.contact_name', $value)
                ->orWhereStartWith('customers.email', $value)
                ->orWhere('customers.reference', $value);
        });
    }

    public function getEmailSearch($query, string $email): QueryBuilder
    {
        return $query->where(function ($query) use ($email) {
            $query->where('customers.email', $email);
        });
    }

    public function getNameSearch($query, string $name): QueryBuilder
    {
        return $query->where(function ($query) use ($name) {
            $query->whereAnyWordStartWith('customers.contact_name', $name);
        });
    }

    public function getReferenceSearch($query, string $ref): QueryBuilder
    {
        return $query->where(function ($query) use ($ref) {
            $query->where('customers.reference', $ref);
        });
    }

    public function asController(Shop $shop, ActionRequest $request): LengthAwarePaginator
    {
        $this->initialisationFromShop($shop, $request);
        return $this->handle($shop, $this->validatedData);
    }


    public function jsonResponse(LengthAwarePaginator $customers): AnonymousResourceCollection
    {
        return CustomersApiResource::collection($customers);
    }

    public function rules(): array
    {
        return [
            'global' => ['nullable', 'string'],
            'name' => ['nullable', 'string'],
            'email' => ['nullable', 'string'],
            'reference' => ['nullable', 'string'],
            'page' => ['nullable', 'integer'],
            'per_page' => ['nullable', 'integer'],
            'sort' => ['nullable', 'string'],
        ];
    }

    public function prepareForValidation(ActionRequest $request): void
    {
        $request->merge(
            [
                'global' => $request->query('global'),
                'page' => $request->query('page', 1),
                'per_page' => $request->query('per_page', 50),
                'sort' => $request->query('sort', 'id'),
            ]
        );
    }

}
