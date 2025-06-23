<?php

/*
 * author Arya Permana - Kirin
 * created on 23-06-2025-11h-47m
 * github: https://github.com/KirinZero0
 * copyright 2025
*/

namespace App\Actions\Api\Customer;

use App\Actions\OrgAction;
use App\Http\Resources\Api\CustomersResource;
use App\Models\Catalogue\Shop;
use App\Models\CRM\Customer;
use Lorisleiva\Actions\ActionRequest;
use App\Services\QueryBuilder;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Arr;

class IndexApiCustomers extends OrgAction
{
    public function handle(Shop $shop, array $modelData): LengthAwarePaginator
    {
        $query = QueryBuilder::for(Customer::class);

        $query->where('customers.shop_id', $shop->id)
            ->leftJoin('customer_stats', 'customers.id', 'customer_stats.customer_id');

        if (Arr::get($modelData, 'search')) {
            $query->where(function ($query) use ($modelData) {
                $query->where('customers.contact_name', 'like', '%' . $modelData['search'] . '%');
            });
        }

        return $query->defaultSort('orders.id')
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

    public function asController(Shop $shop, ActionRequest $request)
    {
        $this->initialisationFromShop($shop, $request);
        return $this->handle($shop, $this->validatedData);
    }


    public function jsonResponse(LengthAwarePaginator $customers): AnonymousResourceCollection
    {
        return CustomersResource::collection($customers);
    }

    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string'],
            'page' => ['nullable', 'integer'],
            'per_page' => ['nullable', 'integer'],
            'sort' => ['nullable', 'string'],
        ];
    }

    public function prepareForValidation(ActionRequest $request): void
    {
        $request->merge(
            [
                'search' => $request->query('search', null),
                'page' => $request->query('page', 1),
                'per_page' => $request->query('per_page', 50),
                'sort' => $request->query('sort', 'id'),
            ]
        );
    }

}
