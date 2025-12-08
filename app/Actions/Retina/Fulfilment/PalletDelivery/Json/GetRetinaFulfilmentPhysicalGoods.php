<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 11 Jul 2024 13:54:36 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Retina\Fulfilment\PalletDelivery\Json;

use App\Actions\RetinaAction;
use App\Http\Resources\Fulfilment\PhysicalGoodsResource;
use App\Models\Catalogue\Product;
use App\Models\Fulfilment\Fulfilment;
use App\Models\Fulfilment\PalletDelivery;
use App\Models\Fulfilment\PalletReturn;
use App\Services\QueryBuilder;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Lorisleiva\Actions\ActionRequest;
use Spatie\QueryBuilder\AllowedFilter;

class GetRetinaFulfilmentPhysicalGoods extends RetinaAction
{
    public function handle(Fulfilment $parent, PalletDelivery|PalletReturn $scope): LengthAwarePaginator
    {
        $globalSearch = AllowedFilter::callback('global', function ($query, $value) {
            $query->where(function ($query) use ($value) {
                $query->whereAnyWordStartWith('products.name', $value)
                    ->orWhereStartWith('products.code', $value);
            });
        });

        $queryBuilder = QueryBuilder::for(Product::class);
        $queryBuilder->where('products.shop_id', $parent->shop_id);
        $queryBuilder->join('assets', 'products.asset_id', '=', 'assets.id');
        $queryBuilder->join('currencies', 'products.currency_id', '=', 'currencies.id');

        $queryBuilder->whereNotIn('products.asset_id', $scope->products()->pluck('asset_id'));

        $queryBuilder
            ->defaultSort('products.id')
            ->select([
                'products.id',
                'products.slug',
                'products.name',
                'products.code',
                'products.state',
                'products.created_at',
                'products.price',
                'products.unit',
                'currencies.code as currency_code',
                'assets.current_historic_asset_id as historic_asset_id',

            ]);

        return $queryBuilder->allowedSorts(['id', 'code', 'name', 'price'])
            ->allowedFilters([$globalSearch])
            ->withPaginator(null)
            ->withQueryString();
    }

    public function authorize(ActionRequest $request): bool
    {
        if ($this->customer->id == $request->route()->parameter('scope')->fulfilmentCustomer->customer_id) {
            return true;
        }

        return false;
    }

    public function inPalletDelivery(Fulfilment $fulfilment, PalletDelivery $scope, ActionRequest $request): LengthAwarePaginator
    {
        $this->initialisation($request);

        return $this->handle($fulfilment, $scope);
    }

    public function inPalletReturn(Fulfilment $fulfilment, PalletReturn $scope, ActionRequest $request): LengthAwarePaginator
    {
        $this->initialisation($request);

        return $this->handle($fulfilment, $scope);
    }

    public function jsonResponse(LengthAwarePaginator $physicalGoods): AnonymousResourceCollection
    {
        return PhysicalGoodsResource::collection($physicalGoods);
    }
}
