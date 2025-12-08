<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 11 Jul 2024 13:54:36 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Retina\Fulfilment\PalletDelivery\Json;

use App\Actions\RetinaAction;
use App\Http\Resources\Fulfilment\ServicesResource;
use App\Models\Billables\Service;
use App\Models\Fulfilment\Fulfilment;
use App\Models\Fulfilment\PalletDelivery;
use App\Models\Fulfilment\PalletReturn;
use App\Services\QueryBuilder;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Lorisleiva\Actions\ActionRequest;
use Spatie\QueryBuilder\AllowedFilter;

class GetRetinaFulfilmentServices extends RetinaAction
{
    public function handle(Fulfilment $parent, PalletDelivery|PalletReturn $scope): LengthAwarePaginator
    {

        $globalSearch = AllowedFilter::callback('global', function ($query, $value) {

            $query->where(function ($query) use ($value) {
                $query->whereAnyWordStartWith('services.name', $value)
                    ->orWhereStartWith('services.code', $value);
            });
        });

        $queryBuilder = QueryBuilder::for(Service::class);
        $queryBuilder->where('services.shop_id', $parent->shop_id);
        $queryBuilder->where('services.is_auto_assign', false);
        $queryBuilder->join('assets', 'services.asset_id', '=', 'assets.id');
        $queryBuilder->join('currencies', 'assets.currency_id', '=', 'currencies.id');

        $queryBuilder->whereNotIn('services.asset_id', $scope->services()->pluck('asset_id'));
        $queryBuilder->where('services.is_pallet_handling', false);

        $queryBuilder
            ->defaultSort('services.id')
            ->select([
                'services.id',
                'services.slug',
                'services.state',
                'services.created_at',
                'services.price',
                'services.unit',
                'assets.name',
                'assets.code',
                'assets.current_historic_asset_id as historic_asset_id',
                'services.description',
                'currencies.code as currency_code',
                'services.is_auto_assign',
                'services.auto_assign_trigger',
                'services.auto_assign_subject',
                'services.auto_assign_subject_type',
                'services.auto_assign_status',
            ]);

        return $queryBuilder->allowedSorts(['code', 'price', 'name', 'state'])
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

    public function jsonResponse(LengthAwarePaginator $services): AnonymousResourceCollection
    {
        return ServicesResource::collection($services);
    }
}
