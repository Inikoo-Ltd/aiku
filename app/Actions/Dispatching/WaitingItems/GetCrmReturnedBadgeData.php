<?php

/*
 * author Louis Perez
 * created on 21-05-2026-15h-52m
 * github: https://github.com/louis-perez
 * copyright 2026
*/

namespace App\Actions\Dispatching\WaitingItems;

use App\Enums\GoodsIn\ReturnDeliveryNote\ReturnDeliveryNoteStateEnum;
use App\Enums\UI\Ordering\OrdersBacklogTabsEnum;
use App\Models\SysAdmin\User;
use Lorisleiva\Actions\Concerns\AsObject;

class GetCrmReturnedBadgeData
{
    use AsObject;

    public function handle(User $user): array
    {
        $organisationsMap = [];

        foreach ($user->authorisedShops()->with("organisation")->get() as $shop) {
            if (!$user->authTo("orders.{$shop->id}.edit")) {
                continue;
            }

            $org = $shop->organisation;

            if (!isset($organisationsMap[$org->slug])) {
                $organisationsMap[$org->slug] = [
                    "organisation" => [
                        "slug" => $org->slug,
                        "name" => $org->name,
                        "code" => $org->code,
                    ],
                    "shops" => [],
                ];
            }

            $waitingCount = $shop
                ->returnDeliveryNotes()
                ->where("return_delivery_notes.state", ReturnDeliveryNoteStateEnum::RETURNED)
                ->count();

            $organisationsMap[$org->slug]["shops"][] = [
                "slug" => $shop->slug,
                "name" => $shop->name,
                "code" => $shop->code,
                "return_crm_items" => [
                    "count" => $waitingCount,
                    "route" => [
                        "name" => "grp.org.shops.show.ordering.backlog",
                        "parameters" => [
                            "organisation"  => $shop->organisation->slug,
                            "shop"          => $shop->slug,
                            "tab"           => OrdersBacklogTabsEnum::RETURNED->value
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

        foreach ($user->authorisedShops()->get() as $shop) {
            if (!$user->authTo("orders.{$shop->id}.edit")) {
                continue;
            }

            $total += $shop
                ->returnDeliveryNotes()
                ->where("return_delivery_notes.state", ReturnDeliveryNoteStateEnum::RETURNED)
                ->count();
        }

        return $total;
    }
}
