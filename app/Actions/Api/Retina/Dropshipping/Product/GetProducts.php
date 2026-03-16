<?php

/*
 * Author: Ganes <gustiganes@gmail.com>
 * Created on: 02-07-2025, Bali, Indonesia
 * Github: https://github.com/Ganes556
 * Copyright: 2025
 *
*/

namespace App\Actions\Api\Retina\Dropshipping\Product;

use App\Actions\Api\Retina\Dropshipping\Resource\ProductsApiResource;
use App\Actions\RetinaApiAction;
use App\Enums\Catalogue\Product\ProductStateEnum;
use App\Enums\Catalogue\Product\ProductStatusEnum;
use App\Models\Catalogue\Product;
use App\Models\Dropshipping\CustomerSalesChannel;
use App\Services\QueryBuilder;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Arr;
use Lorisleiva\Actions\ActionRequest;

class GetProducts extends RetinaApiAction
{
    public function handle(CustomerSalesChannel $customerSalesChannel, array $modelData): LengthAwarePaginator
    {
        $query = QueryBuilder::for(Product::class);

        // Filter by type and value directly, not via global search
        $type = Arr::get($modelData, 'type', 'all');
        $search = Arr::get($modelData, 'search');

        switch (strtolower($type)) {
            case 'department':
                if ($search) {
                    $query->whereHas('department', function ($q) use ($search) {
                        $q->whereAnyWordStartWith('product_categories.name', $search);
                    });
                }
                break;
            case 'family':
                if ($search) {
                    $query->whereHas('family', function ($q) use ($search) {
                        $q->whereAnyWordStartWith('product_categories.name', $search);
                    });
                }
                break;
            case 'sub_department':
                if ($search) {
                    $query->whereHas('subDepartment', function ($q) use ($search) {
                        $q->whereAnyWordStartWith('product_categories.name', $search);
                    });
                }
                break;
            case 'all':
            default:
                if ($search) {
                    $query->where(function ($q) use ($search) {
                        $q->whereAnyWordStartWith('products.name', $search)
                        ->orWhereStartWith('products.code', $search);
                    });
                }
                break;
        }

        $query->where('products.is_for_sale', true);
        $query->where('products.status', ProductStatusEnum::FOR_SALE);
        // Include Discontinuing, as it is basically still for sale
        $query->whereIn('products.state', [ProductStateEnum::ACTIVE, ProductStateEnum::DISCONTINUING]);

        $query->where('products.shop_id', $this->shop->id)
            ->whereNotIn('products.id', function ($subQuery) use ($customerSalesChannel) {
                $subQuery->select('item_id')
                    ->from('portfolios')
                    ->where('item_type', class_basename(Product::class))
                    ->where('customer_id', $customerSalesChannel->customer->id)
                    ->where('platform_id', $customerSalesChannel->platform->id)
                    ->where('customer_sales_channel_id', $customerSalesChannel->id);
            });
        $query->leftJoin('product_categories as department', 'products.department_id', 'department.id');
        $query->leftJoin('product_categories as sub_department', 'products.sub_department_id', 'sub_department.id');
        $query->leftJoin('product_categories as family', 'products.family_id', 'family.id');
        $query->leftJoin('shops', 'products.shop_id', 'shops.id');
        $query->leftJoin('currencies', 'currencies.id', 'products.currency_id');
        $query->leftJoin('product_stats', 'products.id', 'product_stats.product_id');
        $query->leftJoin('media', 'products.image_id', '=', 'media.id');

        $selects = [
            'products.id',
            'products.code',
            'products.name',
            'products.price',
            'products.state',
            'products.available_quantity as current_stock',
            'products.description',
            'products.created_at',
            'products.updated_at',
            'products.gross_weight',
            'products.slug',
            'currencies.code as currency_code',
            'currencies.id as currency_id',
            'department.slug as department_slug',
            'family.slug as family_slug',
            'shops.slug as shop_slug',
        ];

        $include = explode(',', Arr::get($modelData, 'include', ''));


        $isAll = in_array('all', $include);
        $allowedIncludes = [
                'department'        => 'department.name as department_name',
                'sub_department'    => 'sub_department.name as sub_department_name',
                'family'            => 'family.name as family_name'
            ];

        foreach ($allowedIncludes as $key => $includeField) {
            if ($isAll || in_array($key, $include)) {
                $selects[] = $includeField;
            }
        }

        // if(in_array('department', $include)) {
        //     $selects[] = 'department.name as department_name';
        // }

        // if(in_array('sub_department', $include)) {
        //     $selects[] = 'sub_department.name as sub_department_name';
        // }

        // if(in_array('family', $include)) {
        //     $selects[] = 'family.name as family_name';
        // }

        $query->addSelect($selects);

        return $query->defaultSort('products.code')
            ->allowedSorts(['code', 'name', 'shop_slug', 'department_slug', 'family_slug'])
            ->withPaginator(null, queryName: 'per_page')
            ->withQueryString();
    }

    public function jsonResponse(LengthAwarePaginator $products): AnonymousResourceCollection
    {
        return ProductsApiResource::collection($products);
    }

    public function rules(): array
    {
        return [
            'type' => ['nullable', 'string'],
            'search' => ['nullable', 'string'],
            'include' => ['nullable', 'string'],
            'page' => ['nullable', 'integer'],
            'per_page' => ['nullable', 'integer'],
            'sort' => ['nullable', 'string'],
        ];
    }

    public function prepareForValidation(ActionRequest $request): void
    {
        $request->merge([
            'type' => $request->query('type', 'all'),
            'search' => $request->query('search', null),
            'include' => $request->query('include', ''),
            'page' => $request->query('page', 1),
            'per_page' => $request->query('per_page', 50),
            'sort' => $request->query('sort', 'products.code'),
        ]);
    }

    public function asController(ActionRequest $request): LengthAwarePaginator
    {
        $this->initialisationFromDropshipping($request);
        return $this->handle($this->customerSalesChannel, $this->validateAttributes());
    }

}
