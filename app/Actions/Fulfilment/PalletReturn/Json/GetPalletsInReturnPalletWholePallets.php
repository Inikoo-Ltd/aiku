<?php

/*
 * author Arya Permana - Kirin
 * created on 17-02-2025-14h-59m
 * github: https://github.com/KirinZero0
 * copyright 2025
*/

namespace App\Actions\Fulfilment\PalletReturn\Json;

use App\Actions\OrgAction;
use App\Enums\Fulfilment\Pallet\PalletStatusEnum;
use App\Enums\Fulfilment\PalletReturn\PalletReturnStateEnum;
use App\Http\Resources\Fulfilment\PalletReturnItemsUIResource;
use App\InertiaTable\InertiaTable;
use App\Models\CRM\WebUser;
use App\Models\Fulfilment\Pallet;
use App\Models\Fulfilment\PalletReturn;
use App\Services\QueryBuilder;
use Closure;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Lorisleiva\Actions\ActionRequest;
use Spatie\QueryBuilder\AllowedFilter;

class GetPalletsInReturnPalletWholePallets extends OrgAction
{
    public function handle(PalletReturn $palletReturn): LengthAwarePaginator
    {
        $globalSearch = AllowedFilter::callback('global', function ($query, $value) {
            $query->where(function ($query) use ($value) {
                $query->whereAnyWordStartWith('pallets.customer_reference', $value)
                    ->orWhereWith('pallets.reference', $value);
            });
        });

        $query = QueryBuilder::for(Pallet::class);


        $query->where('fulfilment_customer_id', $palletReturn->fulfilment_customer_id);
        $query->physical();

        $query->where(function ($query) use ($palletReturn) {
            $query->where('pallets.pallet_return_id', $palletReturn->id)
                ->orWhereNull('pallets.pallet_return_id');
        });

        if ($palletReturn->state !== PalletReturnStateEnum::DISPATCHED) {
            $query->where('pallets.status', '!=', PalletStatusEnum::RETURNED);
        } elseif ($palletReturn->state === PalletReturnStateEnum::IN_PROCESS) {
            $query->where('pallets.status', PalletStatusEnum::STORING);
        }

        if ($palletReturn->state !== PalletReturnStateEnum::IN_PROCESS) {
            $query->where('pallets.pallet_return_id', $palletReturn->id);
        }

        $query->leftJoin('pallet_return_items', 'pallet_return_items.pallet_id', 'pallets.id');
        $query->leftJoin('locations', 'locations.id', 'pallets.location_id');

        $query->defaultSort('pallets.id')
            ->select(
                'pallet_return_items.id',
                'pallets.id as pallet_id',
                'pallets.slug',
                'pallets.reference',
                'pallets.customer_reference',
                'pallets.notes',
                'pallets.state',
                'pallets.status',
                'pallets.rental_id',
                'pallets.type',
                'pallets.received_at',
                'pallets.location_id',
                'pallets.fulfilment_customer_id',
                'pallets.warehouse_id',
                'pallets.pallet_delivery_id',
                'pallets.pallet_return_id',
                'locations.slug as location_slug',
                'locations.code as location_code'
            );


        return $query->allowedSorts(['customer_reference', 'reference', 'fulfilment_customer_name'])
            ->allowedFilters([$globalSearch])
            ->withPaginator(null)
            ->withQueryString();
    }

    public function authorize(ActionRequest $request): bool
    {
        if ($request->user() instanceof WebUser) {
            return true;
        }

        $this->canEdit   = $request->user()->authTo("fulfilment-shop.{$this->fulfilment->id}.edit");
        $this->canDelete = $request->user()->authTo("fulfilment-shop.{$this->fulfilment->id}.edit");

        return $request->user()->authTo("fulfilment-shop.{$this->fulfilment->id}.view");
    }

    public function jsonResponse(LengthAwarePaginator $pallets): AnonymousResourceCollection
    {
        return PalletReturnItemsUIResource::collection($pallets);
    }

    public function asController(PalletReturn $palletReturn, ActionRequest $request): LengthAwarePaginator
    {
        $this->initialisationFromFulfilment($palletReturn->fulfilment, $request);

        return $this->handle($palletReturn);
    }
    public function tableStructure(PalletReturn $palletReturn, $request, $prefix = null, $modelOperations = []): Closure
    {
        return function (InertiaTable $table) use ($prefix, $modelOperations, $request, $palletReturn) {
            if ($prefix) {
                $table
                    ->name($prefix)
                    ->pageName($prefix . 'Page');
            }

            $emptyStateData = [
                'icons' => ['fal fa-pallet'],
                'title' => '',
                'count' => $palletReturn->fulfilmentCustomer->number_pallets_state_storing
            ];

            $table->withGlobalSearch();


            $table->withEmptyState($emptyStateData)
                ->withModelOperations($modelOperations);


            $table->column(key: 'type_icon', label: ['fal', 'fa-yin-yang'], type: 'icon');


            /* $table->column(key: 'state', label: ['fal', 'fa-yin-yang'], type: 'icon'); */


            $table->column(key: 'reference', label: __('pallet id'), canBeHidden: false, sortable: true, searchable: true);


            $customersReferenceLabel = __("Pallet reference (customer's), notes");


            $table->column(key: 'customer_reference', label: $customersReferenceLabel, canBeHidden: false, sortable: true, searchable: true);

            if (!$request->user() instanceof WebUser) {
                $table->column(key: 'location', label: __('Location'), canBeHidden: false, searchable: true);
            }


            $table->column(key: 'actions', label: 'actions', canBeHidden: false, searchable: true);


            $table->defaultSort('reference');
        };
    }

}
