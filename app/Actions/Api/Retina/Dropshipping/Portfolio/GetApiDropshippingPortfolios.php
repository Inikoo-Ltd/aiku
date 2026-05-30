<?php

/*
 * Author: Ganes <gustiganes@gmail.com>
 * Created on: 13-05-2025, Bali, Indonesia
 * GitHub: https://github.com/Ganes556
 * Copyright: 2025
 *
*/

namespace App\Actions\Api\Retina\Dropshipping\Portfolio;

use App\Actions\RetinaApiAction;
use App\Enums\Catalogue\Product\ProductStateEnum;
use App\Enums\Catalogue\Product\ProductStatusEnum;
use App\Http\Resources\Api\DropshippingApiPortfoliosResource;
use App\Models\Dropshipping\CustomerSalesChannel;
use App\Models\Dropshipping\Portfolio;
use App\Services\QueryBuilder;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Arr;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;
use Lorisleiva\Actions\Concerns\WithAttributes;

class GetApiDropshippingPortfolios extends RetinaApiAction
{
    use AsAction;
    use WithAttributes;

    public function handle(CustomerSalesChannel $customerSalesChannel, array $modelData): LengthAwarePaginator
    {
        if ($customerSalesChannel->customer->is_fulfilment) {
            abort('422');
        }

        $query = QueryBuilder::for(Portfolio::class);
        $query->where('customer_sales_channel_id', $customerSalesChannel->id);
        $query->where('item_type', 'Product');

        if (Arr::get($modelData, 'search')) {
            $query->whereAnyWordStartWith('products.name', $modelData['search']);
        }


        $query->where('portfolios.status', true);

        $query->leftJoin('products', 'products.id', 'portfolios.item_id')
            ->select(
                'portfolios.id',
                'portfolios.item_id',
                'portfolios.item_type',
                'portfolios.created_at',
                'portfolios.updated_at',
                'products.slug as product_slug',
                'products.code as product_code',
                'products.name as product_name',
                'products.available_quantity as available_quantity',
                'products.gross_weight as gross_weight',
                'products.price as price',
                'products.barcode as barcode',
                'products.web_images',
                'products.state as product_state',
                'products.is_for_sale',
            );
        $query->selectRaw("'{$customerSalesChannel->shop->currency->code}'  as currency_code");
        $query->where('products.status', ProductStatusEnum::FOR_SALE);
        $query->where('products.state', ProductStateEnum::ACTIVE);

        return $query->withPaginator(null, queryName: 'per_page')
            ->withQueryString();
    }

    public function asController(ActionRequest $request): LengthAwarePaginator
    {
        $this->initialisationFromDropshipping($request);

        return $this->handle($this->customerSalesChannel, $this->validatedData);
    }


    public function jsonResponse(LengthAwarePaginator $portfolio): AnonymousResourceCollection
    {
        return DropshippingApiPortfoliosResource::collection($portfolio);
    }

    public function rules(): array
    {
        return [
            'search'   => ['nullable', 'string'],
            'page'     => ['nullable', 'integer'],
            'per_page' => ['nullable', 'integer'],
            'sort'     => ['nullable', 'string'],
        ];
    }

    public function prepareForValidation(ActionRequest $request): void
    {
        $request->merge(
            [
                'search'   => $request->query('search'),
                'page'     => $request->query('page', 1),
                'per_page' => $request->query('per_page', 50),
                'sort'     => $request->query('sort', 'id'),
            ]
        );
    }

}
