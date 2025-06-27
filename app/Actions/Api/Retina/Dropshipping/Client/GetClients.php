<?php

/*
 * Author: Ganes <gustiganes@gmail.com>
 * Created on: 14-05-2025, Bali, Indonesia
 * Github: https://github.com/Ganes556
 * Copyright: 2025
 *
*/

namespace App\Actions\Api\Retina\Dropshipping\Client;

use App\Actions\Api\Retina\Dropshipping\Resource\CustomerClientsApiResource;
use App\Actions\RetinaApiAction;
use App\Models\Dropshipping\CustomerClient;
use App\Models\Dropshipping\CustomerSalesChannel;
use App\Services\QueryBuilder;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Arr;
use Lorisleiva\Actions\ActionRequest;

class GetClients extends RetinaApiAction
{
    public function handle(CustomerSalesChannel $customerSalesChannel, array $modelData): LengthAwarePaginator
    {
        $query = QueryBuilder::for(CustomerClient::class);

        $query->where('customer_clients.customer_sales_channel_id', $customerSalesChannel->id);

        if (Arr::get($modelData, 'search')) {
            $query->where(function ($query) use ($modelData) {
                $value = $modelData['search'];
                $query->whereAnyWordStartWith('customer_clients.name', $value)
                    ->orWhereStartWith('customer_clients.email', $value)
                    ->orWhere('customer_clients.reference', '=', $value);
            });
        }

        if (Arr::get($modelData, 'active') === 'true') {
            $query->where('customer_clients.status', true);
        } elseif (Arr::get($modelData, 'active') === 'false') {
            $query->where('customer_clients.status', false);
        }

        return $query
        ->defaultSort('customer_clients.reference')
        ->select([
            'customer_clients.location',
            'customer_clients.reference',
            'customer_clients.id',
            'customer_clients.status',
            'customer_clients.name',
            'customer_clients.contact_name',
            'customer_clients.company_name',
            'customer_clients.email',
            'customer_clients.ulid',
            'customer_clients.created_at'
        ])
        ->allowedSorts(['reference', 'name', 'created_at'])
        ->withPaginator(null, queryName: 'per_page')
        ->withQueryString();
    }

    public function asController(ActionRequest $request): LengthAwarePaginator
    {
        $this->initialisationFromDropshipping($request);
        return $this->handle($this->customerSalesChannel, $this->validatedData);
    }


    public function jsonResponse(LengthAwarePaginator $customerClients): AnonymousResourceCollection
    {
        return CustomerClientsApiResource::collection($customerClients);
    }

    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string'],
            'active' => ['nullable','string', 'in:true,false'],
            'page' => ['nullable', 'integer'],
            'per_page' => ['nullable', 'integer'],
            'sort' => ['nullable', 'string'],
        ];
    }

    public function prepareForValidation(ActionRequest $request): void
    {
        $active = $request->query('active');

        $request->merge(
            [
                'search' => $request->query('search', null),
                'active' => $active,
                'page' => $request->query('page', 1),
                'per_page' => $request->query('per_page', 50),
                'sort' => $request->query('sort', 'id'),
            ]
        );
    }

}
