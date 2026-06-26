<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 19 May 2024 12:26:53 British Summer Time, Sheffield, UK
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Fulfilment;

use App\Enums\Fulfilment\PalletReturn\PalletReturnStateEnum;
use App\Enums\Fulfilment\PalletReturn\PalletReturnTypeEnum;
use App\Models\Fulfilment\PalletReturn;
use App\Models\Fulfilment\Fulfilment;
use App\Models\Inventory\Warehouse;
use Lorisleiva\Actions\ActionRequest;

trait WithPalletReturnSubNavigation
{
    public function getPalletReturnSubNavigation(Fulfilment|Warehouse $parent, ActionRequest $request): array
    {
        $subNavigation = [];

        $routeParameters = $request->route()->originalParameters();
        $selectedType = $this->resolvePalletReturnTypeFromRequest($request);

        $typeQueryParams = $this->buildTypeQueryParams($request, $selectedType);

        $types = [
            PalletReturnTypeEnum::PALLET->value,
            PalletReturnTypeEnum::STORED_ITEM->value,
        ];

        $countsByType = PalletReturn::query()
            ->when(
                $parent instanceof Warehouse,
                fn ($query) => $query->where('warehouse_id', $parent->id),
                fn ($query) => $query->where('fulfilment_id', $parent->id)
            )
            ->whereIn('type', $types)
            ->toBase()
            ->selectRaw('type, COUNT(*) as aggregate')
            ->groupBy('type')
            ->pluck('aggregate', 'type')
            ->all();

        $countsByTypeAndState = PalletReturn::query()
            ->when(
                $parent instanceof Warehouse,
                fn ($query) => $query->where('warehouse_id', $parent->id),
                fn ($query) => $query->where('fulfilment_id', $parent->id)
            )
            ->whereIn('type', $types)
            ->whereIn('state', [
                PalletReturnStateEnum::CONFIRMED->value,
                PalletReturnStateEnum::PICKING->value,
                PalletReturnStateEnum::PICKED->value,
                PalletReturnStateEnum::DISPATCHED->value,
                PalletReturnStateEnum::CANCEL->value,
            ])
            ->toBase()
            ->selectRaw('type, state, COUNT(*) as aggregate')
            ->groupBy('type', 'state')
            ->get()
            ->reduce(function (array $carry, $row): array {
                $carry[$row->type][$row->state] = (int) $row->aggregate;
                return $carry;
            }, []);

        $currentRouteName = (string) $request->route()->getName();

        $subNavigation[] = [
            'route' => [
                'name'       => $currentRouteName,
                'parameters' => array_merge(
                    $routeParameters,
                    $this->preserveElementQueryParams($request),
                    $this->buildTypeQueryParams($request, PalletReturnTypeEnum::PALLET->value)
                ),
            ],
            'label' => __('Fulfilment Pallet'),
            'leftIcon' => PalletReturnTypeEnum::stateIcon()[PalletReturnTypeEnum::PALLET->value],
            'active' => $selectedType === PalletReturnTypeEnum::PALLET->value
        ];

        $subNavigation[] = [
            'route' => [
                'name'       => $currentRouteName,
                'parameters' => array_merge(
                    $routeParameters,
                    $this->preserveElementQueryParams($request),
                    $this->buildTypeQueryParams($request, PalletReturnTypeEnum::STORED_ITEM->value)
                ),
            ],
            'label' => __('Fulfilment DS'),
            'leftIcon' => PalletReturnTypeEnum::stateIcon()[PalletReturnTypeEnum::STORED_ITEM->value],
            'active' => $selectedType === PalletReturnTypeEnum::STORED_ITEM->value
        ];

        $subNavigation[] = [

            'route' => [
                'name' => match (class_basename($parent)) {
                    'Fulfilment' => 'grp.org.fulfilments.show.operations.pallet-returns.new.index',
                    'Warehouse' => 'grp.org.warehouses.show.dispatching.pallet-returns.confirmed.index'
                },
                'parameters' => match (class_basename($parent)) {
                    'Fulfilment' => array_merge($routeParameters, ['returns_elements[state]' => 'confirmed'], $typeQueryParams),
                    'Warehouse' => array_merge($routeParameters, $typeQueryParams)
                }

            ],

            'label' => __('To do'),
            'leftIcon' => [
                'icon' => 'fal fa-stream',
                'tooltip' => __('To do'),
            ],
            'number' => (int) ($countsByTypeAndState[$selectedType][PalletReturnStateEnum::CONFIRMED->value] ?? 0)
        ];

        $subNavigation[] = [
            'route' => [
                'name' => match (class_basename($parent)) {
                    'Fulfilment' => 'grp.org.fulfilments.show.operations.pallet-returns.picking.index',
                    'Warehouse' => 'grp.org.warehouses.show.dispatching.pallet-returns.picking.index'
                },
                'parameters' => array_merge($routeParameters, $typeQueryParams)
            ],

            'label' => __("Picking"),
            'leftIcon' => [
                'icon' => 'fal fa-parking',
                'tooltip' => __("Picking"),
            ],
            'number' => (int) ($countsByTypeAndState[$selectedType][PalletReturnStateEnum::PICKING->value] ?? 0)
        ];

        $subNavigation[] = [
            'route' => [
                'name' => match (class_basename($parent)) {
                    'Fulfilment' => 'grp.org.fulfilments.show.operations.pallet-returns.picked.index',
                    'Warehouse' => 'grp.org.warehouses.show.dispatching.pallet-returns.picked.index'
                },
                'parameters' => array_merge($routeParameters, $typeQueryParams)
            ],

            'label' => __("Picked"),
            'leftIcon' => [
                'icon' => 'fal fa-parking',
                'tooltip' => __("Picked"),
            ],
            'number' => (int) ($countsByTypeAndState[$selectedType][PalletReturnStateEnum::PICKED->value] ?? 0)
        ];

        $subNavigation[] = [
            'route' => [
                'name' => match (class_basename($parent)) {
                    'Fulfilment' => 'grp.org.fulfilments.show.operations.pallet-returns.dispatched.index',
                    'Warehouse' => 'grp.org.warehouses.show.dispatching.pallet-returns.dispatched.index'
                },
                'parameters' => array_merge($routeParameters, $typeQueryParams)
            ],

            'label' => __("Dispatched"),
            "align"    => "right",
            'leftIcon' => [
                'icon' => 'fal fa-parking',
                'tooltip' => __("Dispatched"),
            ],
            'number' => (int) ($countsByTypeAndState[$selectedType][PalletReturnStateEnum::DISPATCHED->value] ?? 0)
        ];

        $subNavigation[] = [
            'route' => [
                'name' => match (class_basename($parent)) {
                    'Fulfilment' => 'grp.org.fulfilments.show.operations.pallet-returns.cancelled.index',
                    'Warehouse' => 'grp.org.warehouses.show.dispatching.pallet-returns.cancelled.index'
                },
                'parameters' => array_merge($routeParameters, $typeQueryParams)
            ],

            'label' => __("Cancelled"),
            "align"    => "right",
            'leftIcon' => [
                'icon' => 'fal fa-parking',
                'tooltip' => __("Cancelled"),
            ],
            'number' => (int) ($countsByTypeAndState[$selectedType][PalletReturnStateEnum::CANCEL->value] ?? 0)
        ];

        $subNavigation[] = [
            'route' => [
                'name' => match (class_basename($parent)) {
                    'Fulfilment' => 'grp.org.fulfilments.show.operations.pallet-returns.index',
                    'Warehouse' => 'grp.org.warehouses.show.dispatching.pallet-returns.index'
                },
                'parameters' => array_merge($routeParameters, $typeQueryParams)
            ],

            'label' => __("All"),
            "align"    => "right",
            'leftIcon' => [
                'icon' => 'fal fa-parking',
                'tooltip' => __("All"),
            ],
            'number' => (int) ($countsByType[$selectedType] ?? 0)
        ];

        return $subNavigation;
    }

    private function resolvePalletReturnTypeFromRequest(ActionRequest $request): string
    {
        $rawType = $request->input('returns_filter.type')
            ?? $request->input('filter.type');

        if (!is_string($rawType) || $rawType === '') {
            return PalletReturnTypeEnum::PALLET->value;
        }

        return PalletReturnTypeEnum::tryFrom($rawType)?->value ?? PalletReturnTypeEnum::PALLET->value;
    }

    private function buildTypeQueryParams(ActionRequest $request, string $type): array
    {
        $params = [];

        if ($request->has('returns_filter') && is_array($request->input('returns_filter'))) {
            $params['returns_filter'] = array_merge($request->input('returns_filter'), ['type' => $type]);
        } else {
            $params['returns_filter'] = ['type' => $type];
        }

        $params['filter'] = ['type' => $type];

        return $params;
    }

    private function preserveElementQueryParams(ActionRequest $request): array
    {
        if (!$request->has('returns_elements')) {
            return [];
        }

        $elements = $request->input('returns_elements');
        if (!is_array($elements) || $elements === []) {
            return [];
        }

        return ['returns_elements' => $elements];
    }
}
