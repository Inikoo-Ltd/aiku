<?php

/*
 * Author: Ganes <gustiganes@gmail.com>
 * Created on: 14-05-2025, Bali, Indonesia
 * Github: https://github.com/Ganes556
 * Copyright: 2025
 *
*/

namespace App\Actions\Retina\Api\Client;

use App\Actions\RetinaWebhookAction;
use App\Http\Resources\Api\CustomerClientsResource;
use App\Models\Dropshipping\CustomerClient;
use App\Models\Dropshipping\CustomerSalesChannel;
use Lorisleiva\Actions\ActionRequest;
use App\Services\QueryBuilder;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Arr;

class GetClients extends RetinaWebhookAction
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

    public function asController(CustomerSalesChannel $customerSalesChannel, ActionRequest $request): LengthAwarePaginator
    {
        $this->initialisation($request);
        return $this->handle($customerSalesChannel, $this->validateAttributes());
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
