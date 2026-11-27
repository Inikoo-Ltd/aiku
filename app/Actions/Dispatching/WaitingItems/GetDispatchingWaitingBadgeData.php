<?php

/*
 * Author: Steven Wicca stewicalf@gmail.com
 * Created: Wed, 16 Apr 2026 00:00:00 Central Indonesia Time, Bali, Indonesia
 * Copyright (c) 2026, Steven Wicca Alfredo
 */

namespace App\Actions\Dispatching\WaitingItems;

use App\Enums\Dispatching\DeliveryNote\DeliveryNoteStateEnum;
use App\Enums\UI\Dispatch\WaitingItemsTabsEnum;
use App\Models\Inventory\Warehouse;
use App\Models\SysAdmin\User;
use Lorisleiva\Actions\Concerns\AsObject;

class GetDispatchingWaitingBadgeData
{
    use AsObject;

    public function handle(User $user): array
    {
        $organisationsMap = [];

        foreach ($user->authorisedWarehouses()->with("organisation")->get() as $warehouse) {
            if (!$user->authTo("dispatching.{$warehouse->id}.view")) {
                continue;
            }

            $orgSlug = $warehouse->organisation->slug;

            if (!isset($organisationsMap[$orgSlug])) {
                $organisationsMap[$orgSlug] = [
                    "organisation" => [
                        "slug" => $warehouse->organisation->slug,
                        "name" => $warehouse->organisation->name,
                        "code" => $warehouse->organisation->code,
                    ],
                    "warehouses" => [],
                ];
            }

            $waitingCount = $warehouse
                ->deliveryNotes()
                ->join(
                    "delivery_note_items",
                    "delivery_notes.id",
                    "=",
                    "delivery_note_items.delivery_note_id",
                )
                ->where("delivery_note_items.has_waiting_warehouse", true)
                ->whereNull("delivery_note_items.waiting_production_at")
                ->where("delivery_notes.state", DeliveryNoteStateEnum::HANDLING_BLOCKED)
                ->count();

            $stillPickingCount = $warehouse
                ->deliveryNotes()
                ->join(
                    "delivery_note_items",
                    "delivery_notes.id",
                    "=",
                    "delivery_note_items.delivery_note_id",
                )
                ->where("delivery_note_items.has_waiting_warehouse", true)
                ->whereNull("delivery_note_items.waiting_production_at")
                ->where("delivery_notes.state", DeliveryNoteStateEnum::HANDLING)
                ->count();

            $waitingProductionCount = $this->waitingProductionCount($warehouse);

            $organisationsMap[$orgSlug]["warehouses"][] = [
                "slug" => $warehouse->slug,
                "name" => $warehouse->name,
                "code" => $warehouse->code,
                "waiting_items" => [
                    "count" => $waitingCount,
                    "route" => [
                        "name" => "grp.org.warehouses.show.dispatching.waiting_items",
                        "parameters" => [
                            "organisation" => $warehouse->organisation->slug,
                            "warehouse" => $warehouse->slug,
                        ],
                    ],
                ],
                "waiting_items_production" => [
                    "count" => $waitingProductionCount,
                    "route" => [
                        "name" => "grp.org.warehouses.show.dispatching.waiting_items",
                        "parameters" => [
                            "organisation" => $warehouse->organisation->slug,
                            "warehouse" => $warehouse->slug,
                            "tab" => WaitingItemsTabsEnum::WAITING_PRODUCTION->value,
                        ],
                    ],
                ],
                "waiting_items_still_picking" => [
                    "count" => $stillPickingCount,
                    "route" => [
                        "name" => "grp.org.warehouses.show.dispatching.waiting_items_still_picking",
                        "parameters" => [
                            "organisation" => $warehouse->organisation->slug,
                            "warehouse" => $warehouse->slug,
                        ],
                    ],
                ],
            ];
        }

        return array_values($organisationsMap);
    }

    public function totalCount(User $user): int
    {
        $total = 0;

        foreach ($user->authorisedWarehouses()->get() as $warehouse) {
            if (!$user->authTo("dispatching.{$warehouse->id}.view")) {
                continue;
            }

            $total += $warehouse
                ->deliveryNotes()
                ->join('delivery_note_items', 'delivery_notes.id', '=', 'delivery_note_items.delivery_note_id')
                ->where('delivery_note_items.has_waiting_warehouse', true)
                ->whereNull('delivery_note_items.waiting_production_at')
                ->whereIn('delivery_notes.state', [DeliveryNoteStateEnum::HANDLING_BLOCKED, DeliveryNoteStateEnum::HANDLING])
                ->count();
        }

        return $total;
    }

    public function productionTotalCount(User $user): int
    {
        $total = 0;

        foreach ($user->authorisedWarehouses()->get() as $warehouse) {
            if ($user->authTo("dispatching.{$warehouse->id}.view")) {
                $total += $this->waitingProductionCount($warehouse);
            }
        }

        return $total;
    }

    private function waitingProductionCount(Warehouse $warehouse): int
    {
        return $warehouse
            ->deliveryNotes()
            ->join('delivery_note_items', 'delivery_notes.id', '=', 'delivery_note_items.delivery_note_id')
            ->where('delivery_note_items.has_waiting_warehouse', true)
            ->whereNotNull('delivery_note_items.waiting_production_at')
            ->whereIn('delivery_notes.state', [DeliveryNoteStateEnum::HANDLING_BLOCKED, DeliveryNoteStateEnum::HANDLING])
            ->count();
    }
}
