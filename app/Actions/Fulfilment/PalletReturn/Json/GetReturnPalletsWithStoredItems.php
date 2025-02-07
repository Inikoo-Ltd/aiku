<?php

/*
 * author Arya Permana - Kirin
 * created on 07-02-2025-09h-36m
 * github: https://github.com/KirinZero0
 * copyright 2025
*/

namespace App\Actions\Fulfilment\PalletReturn\Json;

use App\Actions\OrgAction;
use App\Enums\Fulfilment\Pallet\PalletStateEnum;
use App\Enums\Fulfilment\Pallet\PalletStatusEnum;
use App\Enums\Fulfilment\PalletReturn\PalletReturnStateEnum;
use App\Enums\Fulfilment\StoredItem\StoredItemInReturnOptionEnum;
use App\Http\Resources\Fulfilment\PalletsResource;
use App\InertiaTable\InertiaTable;
use App\Models\Fulfilment\FulfilmentCustomer;
use App\Models\Fulfilment\Pallet;
use App\Models\Fulfilment\PalletReturn;
use Closure;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Lorisleiva\Actions\ActionRequest;
use App\Models\CRM\WebUser;
use Spatie\QueryBuilder\AllowedFilter;
use App\Services\QueryBuilder;

class GetReturnPalletsWithStoredItems extends OrgAction
{
    protected function getElementGroups(PalletReturn $palletReturn): array
    {
        return [
            'option' => [
                'label'    => __('Option'),
                'elements' => array_merge_recursive(
                    StoredItemInReturnOptionEnum::labels(),
                    StoredItemInReturnOptionEnum::count()
                ),
                'engine' => function ($query, $elements) use ($palletReturn) {
                    if (in_array(StoredItemInReturnOptionEnum::SELECTED->value, $elements)) {
                        $query->where('pallet_return_items.pallet_return_id', $palletReturn->id);
                    } elseif (in_array(StoredItemInReturnOptionEnum::UNSELECTED->value, $elements)) {
                        $query->whereNull('pallets.pallet_return_id')
                            ->where('pallets.state', PalletStateEnum::STORING);
                    }
                }
            ],
        ];
    }

    public function handle(PalletReturn $palletReturn, $prefix = null): LengthAwarePaginator
    {
        $globalSearch = AllowedFilter::callback('global', function ($query, $value) {
            $query->where(function ($query) use ($value) {
                $query->whereAnyWordStartWith('pallets.customer_reference', $value)
                    ->orWhereWith('pallets.reference', $value);
            });
        });

        if ($prefix) {
            InertiaTable::updateQueryBuilderParameters($prefix);
        }

        $query = QueryBuilder::for(Pallet::class);

        $query->where('fulfilment_customer_id', $palletReturn->fulfilment_customer_id);

        $query->where(function ($query) use ($palletReturn) {
            $query->where('pallets.pallet_return_id', $palletReturn->id)
            ->orWhereNull('pallets.pallet_return_id');
        });

        if ($palletReturn->state !== PalletReturnStateEnum::DISPATCHED) {
            $query->where('pallets.status', '!=', PalletStatusEnum::RETURNED);
        } elseif ($palletReturn->state === PalletReturnStateEnum::IN_PROCESS) {
            $query->where('pallets.state', PalletStatusEnum::STORING);
        }

        if ($palletReturn->state !== PalletReturnStateEnum::IN_PROCESS) {
            $query->where('pallets.pallet_return_id', $palletReturn->id);
        }

        $query->leftJoin('pallet_return_items', 'pallet_return_items.pallet_id', 'pallets.id');
        $query->leftJoin('locations', 'locations.id', 'pallets.location_id');
        $query->leftJoin('pallet_stored_items', 'pallet_stored_items.pallet_id', 'pallets.id');

        $query->whereNotNull('pallet_stored_items.id')
            ->distinct('pallets.id');

        if ($palletReturn->state === PalletReturnStateEnum::IN_PROCESS) {
            foreach ($this->getElementGroups($palletReturn) as $key => $elementGroup) {
                $query->whereElementGroup(
                    key: $key,
                    allowedElements: array_keys($elementGroup['elements']),
                    engine: $elementGroup['engine'],
                    prefix: $prefix
                );
            }
        }

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
                'locations.slug as location_code'
            );


        return $query->allowedSorts(['customer_reference', 'reference', 'fulfilment_customer_name'])
            ->allowedFilters([$globalSearch])
            ->withPaginator($prefix)
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
        return PalletsResource::collection($pallets);
    }

    public function asController(FulfilmentCustomer $fulfilmentCustomer, ActionRequest $request): LengthAwarePaginator
    {
        $this->initialisationFromFulfilment($fulfilmentCustomer->fulfilment, $request);

        return $this->handle($fulfilmentCustomer);
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

            if ($palletReturn->state === PalletReturnStateEnum::IN_PROCESS) {
                foreach ($this->getElementGroups($palletReturn) as $key => $elementGroup) {
                    $table->elementGroup(
                        key: $key,
                        label: $elementGroup['label'],
                        elements: $elementGroup['elements']
                    );
                }
            }

            $table->withGlobalSearch();


            $table->withEmptyState($emptyStateData)
                ->withModelOperations($modelOperations);


            $table->column(key: 'type_icon', label: ['fal', 'fa-yin-yang'], type: 'icon');


            /* $table->column(key: 'state', label: ['fal', 'fa-yin-yang'], type: 'icon'); */


            $table->column(key: 'reference', label: __('reference'), canBeHidden: false, sortable: true, searchable: true);


            $customersReferenceLabel = __("Pallet reference (customer's), notes");


            $table->column(key: 'customer_reference', label: $customersReferenceLabel, canBeHidden: false, sortable: true, searchable: true);
            $table->column(key: 'stored_items', label: __('Stored items'), canBeHidden: false, sortable: true, searchable: true);

            if (!$request->user() instanceof WebUser) {
                $table->column(key: 'location', label: __('Location'), canBeHidden: false, searchable: true);
            }


            $table->column(key: 'actions', label: 'actions', canBeHidden: false, searchable: true);


            $table->defaultSort('reference');
        };
    }

}
