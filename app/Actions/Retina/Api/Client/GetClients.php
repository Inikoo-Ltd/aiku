<?php

/*
 * Author: Ganes <gustiganes@gmail.com>
 * Created on: 14-05-2025, Bali, Indonesia
 * Github: https://github.com/Ganes556
 * Copyright: 2025
 *
*/

namespace App\Actions\Retina\Api\Client;

use App\Http\Resources\Api\CustomerClientsResource;
use App\Models\CRM\Customer;
use App\Models\Dropshipping\CustomerClient;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;
use App\Services\QueryBuilder;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Arr;
use Lorisleiva\Actions\Concerns\WithAttributes;

class GetClients
{
    use AsAction;
    use WithAttributes;

    public function handle(Customer $customer, array $modelData): LengthAwarePaginator
    {
        $query = QueryBuilder::for(CustomerClient::class);

        $platform = $customer->platforms()
            ->where('type', 'manual')
            ->first();

        $query->where('customer_clients.customer_id', $customer->id);
        $query->where('customer_clients.platform_id', $platform->id);


        if (Arr::get($modelData, 'search')) {
            $query->where(function ($query) use ($modelData) {
                $value = $modelData['search'];
                $query->whereAnyWordStartWith('customer_clients.name', $value)
                    ->orWhereStartWith('customer_clients.email', $value)
                    ->orWhere('customer_clients.reference', '=', $value);
            });
        }

        return $query
        ->defaultSort('customer_clients.reference')
        ->select([
            'customer_clients.location',
            'customer_clients.reference',
            'customer_clients.id',
            'customer_clients.name',
            'customer_clients.ulid',
            'customer_clients.created_at'
        ])
        ->allowedSorts(['reference', 'name', 'created_at'])
        ->withPaginator(null, queryName: 'per_page')
        ->withQueryString();
    }

    public function asController(ActionRequest $request): LengthAwarePaginator
    {
        $customer = $request->user();
        $this->fillFromRequest($request);
        return $this->handle($customer, $this->validateAttributes());
    }


    public function jsonResponse(LengthAwarePaginator $customerClients): AnonymousResourceCollection
    {
        return CustomerClientsResource::collection($customerClients);
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
