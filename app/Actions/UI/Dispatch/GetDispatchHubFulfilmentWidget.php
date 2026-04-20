<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 01 Jun 2025 09:58:36 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\UI\Dispatch;

use App\Enums\Catalogue\Shop\ShopTypeEnum;
use App\Enums\Dispatching\DeliveryNote\DeliveryNoteStateEnum;
use App\Enums\Fulfilment\PalletReturn\PalletReturnStateEnum;
use App\Enums\Fulfilment\PalletReturn\PalletReturnTypeEnum;
use App\Models\Fulfilment\PalletReturn;
use App\Models\Inventory\Warehouse;
use Illuminate\Support\Facades\Route;
use Lorisleiva\Actions\Concerns\AsObject;

class GetDispatchHubFulfilmentWidget
{
    use AsObject;

    public function handle(Warehouse $warehouse): array
    {
        return [
            $this->buildWidget($warehouse, PalletReturnTypeEnum::PALLET, __('Fulfilment Pallet'), 'fulfilment_pallet'),
            $this->buildWidget($warehouse, PalletReturnTypeEnum::STORED_ITEM, __('Fulfilment DS'), 'fulfilment_ds'),
        ];
    }

    private function buildWidget(Warehouse $warehouse, PalletReturnTypeEnum $type, string $label, string $slug): array
    {
        $organisation = $warehouse->organisation;

        $routeParameters = request()->route()?->originalParameters() ?? [
            'organisation' => $organisation->slug,
            'warehouse'    => $warehouse->slug,
        ];

        $typeFilter = ['filter[type]' => $type->value];

        $isFulfilmentContext = array_key_exists('fulfilment', $routeParameters)
            || str_contains((string) request()->route()?->getName(), 'fulfilments.');

        $counts = PalletReturn::query()
            ->where('warehouse_id', $warehouse->id)
            ->where('type', $type->value)
            ->whereIn('state', [
                PalletReturnStateEnum::CONFIRMED->value,
                PalletReturnStateEnum::PICKING->value,
                PalletReturnStateEnum::PICKED->value,
            ])
            ->selectRaw('state, COUNT(*) as aggregate')
            ->groupBy('state')
            ->pluck('aggregate', 'state');

        $todo     = (int) ($counts[PalletReturnStateEnum::CONFIRMED->value] ?? 0);
        $handling = (int) ($counts[PalletReturnStateEnum::PICKING->value] ?? 0);
        $picked   = (int) ($counts[PalletReturnStateEnum::PICKED->value] ?? 0);

        $routeIndex     = 'grp.org.warehouses.show.dispatching.pallet-returns.index';
        $routeConfirmed = $isFulfilmentContext
            ? 'grp.org.fulfilments.show.operations.pallet-returns.new.index'
            : 'grp.org.warehouses.show.dispatching.pallet-returns.confirmed.index';
        $routePicking = $isFulfilmentContext
            ? 'grp.org.fulfilments.show.operations.pallet-returns.picking.index'
            : 'grp.org.warehouses.show.dispatching.pallet-returns.picking.index';
        $routePicked = $isFulfilmentContext
            ? 'grp.org.fulfilments.show.operations.pallet-returns.picked.index'
            : 'grp.org.warehouses.show.dispatching.pallet-returns.picked.index';

        $todoRouteParameters = $isFulfilmentContext
            ? array_merge($routeParameters, ['returns_elements[state]' => 'confirmed'], $typeFilter)
            : array_merge($routeParameters, $typeFilter);

        $shopType = ShopTypeEnum::FULFILMENT->value;

        return [
            'slug'        => $slug,
            'label'       => $label,
            'tooltip'     => $label,
            'total_route' => Route::has($routeIndex) ? [
                'name'       => $routeIndex,
                'parameters' => array_merge($routeParameters, $typeFilter),
            ] : null,
            'waiting_items_still_picking' => [
                'count' => $warehouse->deliveryNotes()
                    ->join('delivery_note_items', 'delivery_notes.id', '=', 'delivery_note_items.delivery_note_id')
                    ->leftJoin('shops', 'delivery_notes.shop_id', '=', 'shops.id')
                    ->where('shops.type', $shopType)
                    ->where('delivery_note_items.has_waiting_warehouse', true)
                    ->where('delivery_notes.state', DeliveryNoteStateEnum::HANDLING)
                    ->count(),
                'route' => [
                    'name'       => 'grp.org.warehouses.show.dispatching.waiting_items_still_picking.shop',
                    'parameters' => [$organisation->slug, $warehouse->slug, $shopType],
                ],
            ],
            'waiting_items' => [
                'count' => $warehouse->deliveryNotes()
                    ->join('delivery_note_items', 'delivery_notes.id', '=', 'delivery_note_items.delivery_note_id')
                    ->leftJoin('shops', 'delivery_notes.shop_id', '=', 'shops.id')
                    ->where('shops.type', $shopType)
                    ->where('delivery_note_items.has_waiting_warehouse', true)
                    ->where('delivery_notes.state', '!=', DeliveryNoteStateEnum::HANDLING)
                    ->count(),
                'route' => [
                    'name'       => 'grp.org.warehouses.show.dispatching.waiting_items.shop',
                    'parameters' => [$organisation->slug, $warehouse->slug, $shopType],
                ],
            ],
            'waiting_crm_items_still_picking' => [
                'count' => (int) PalletReturn::query()
                    ->where('warehouse_id', $warehouse->id)
                    ->where('type', $type->value)
                    ->where('state', PalletReturnStateEnum::PICKING->value)
                    ->sum('number_items_waiting_crm'),
                'route' => [
                    'name'       => '',
                    'parameters' => [],
                ],
            ],
            'waiting_crm_items' => [
                'count' => $warehouse->deliveryNotes()
                    ->join('delivery_note_items', 'delivery_notes.id', '=', 'delivery_note_items.delivery_note_id')
                    ->leftJoin('shops', 'delivery_notes.shop_id', '=', 'shops.id')
                    ->where('shops.type', $shopType)
                    ->where('delivery_note_items.has_waiting_crm', true)
                    ->where('delivery_notes.state', DeliveryNoteStateEnum::HANDLING_BLOCKED)
                    ->count(),
                'route' => [
                    'name'       => 'grp.org.warehouses.show.dispatching.waiting_crm_items.shop',
                    'parameters' => [$organisation->slug, $warehouse->slug, $shopType],
                ],
            ],
            'cases'       => [
                'todo'    => [
                    'route' => Route::has($routeConfirmed) ? [
                        'name'       => $routeConfirmed,
                        'parameters' => $todoRouteParameters,
                    ] : null,
                ],
                'handling' => [
                    'route' => Route::has($routePicking) ? [
                        'name'       => $routePicking,
                        'parameters' => array_merge($routeParameters, $typeFilter),
                    ] : null,
                ],
                'picked'  => [
                    'route' => Route::has($routePicked) ? [
                        'name'       => $routePicked,
                        'parameters' => array_merge($routeParameters, $typeFilter),
                    ] : null,
                ],
            ],
            'todo'        => $todo,
            'handling'    => $handling,
            'picked'      => $picked,
            'total'       => $todo + $handling + $picked,
        ];
    }
}
