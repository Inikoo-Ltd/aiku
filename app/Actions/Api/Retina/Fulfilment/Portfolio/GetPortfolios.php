<?php

/*
 * Author: Ganes <gustiganes@gmail.com>
 * Created on: 13-05-2025, Bali, Indonesia
 * Github: https://github.com/Ganes556
 * Copyright: 2025
 *
*/

namespace App\Actions\Api\Retina\Fulfilment\Portfolio;

use App\Actions\RetinaApiAction;
use App\Http\Resources\Api\PortfoliosResource;
use App\Models\Catalogue\Product;
use App\Models\Dropshipping\CustomerSalesChannel;
use App\Models\Dropshipping\Portfolio;
use App\Models\Fulfilment\StoredItem;
use App\Services\QueryBuilder;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Arr;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;
use Lorisleiva\Actions\Concerns\WithAttributes;

class GetPortfolios extends RetinaApiAction
{
    use AsAction;
    use WithAttributes;

    public function handle(CustomerSalesChannel $customerSalesChannel, array $modelData): LengthAwarePaginator
    {
        $query = QueryBuilder::for(Portfolio::class);

        $query->where('customer_sales_channel_id', $customerSalesChannel->id);

        $query->with(['item']);

        if (Arr::get($modelData, 'search')) {
            $query->whereHas('item', function ($query) use ($modelData) {
                $query->whereAnyWordStartWith('name', $modelData['search']);
            });
        }

        if ($customerSalesChannel->customer->is_fulfilment) {
            $query->where('item_type', class_basename(StoredItem::class));
        } else {
            $query->where('item_type', class_basename(Product::class));
        }


        return $query->withPaginator(null, queryName: 'per_page')
        ->withQueryString();
    }

    public function asController(ActionRequest $request): LengthAwarePaginator
    {
        $this->initialisationFromFulfilment($request);
        return $this->handle($this->customerSalesChannel, $this->validatedData);
    }


    public function jsonResponse(LengthAwarePaginator $portfolio): AnonymousResourceCollection
    {
        return PortfoliosResource::collection($portfolio);
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
