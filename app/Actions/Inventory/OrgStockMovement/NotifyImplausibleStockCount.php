<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 22 Nov 2026 Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Inventory\OrgStockMovement;

use App\Actions\SysAdmin\User\NotifyUserOnChosenChannels;
use App\Enums\SysAdmin\User\UserNotificationEnum;
use App\Models\Inventory\OrgStockMovement;
use App\Models\SysAdmin\User;
use App\Notifications\ImplausibleStockCountNotification;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * HELP-4868: a count that tripped the implausible count warning (AuditLocationOrgStock::countWarning) is sent, once saved,
 * to every active user who subscribed to it and can see the stock of that warehouse.
 */
class NotifyImplausibleStockCount
{
    use AsAction;

    public function handle(OrgStockMovement $orgStockMovement): int
    {
        if (!isset($orgStockMovement->data['count_warning'])) {
            return 0;
        }

        $event = UserNotificationEnum::IMPLAUSIBLE_STOCK_COUNT;
        $notification = new ImplausibleStockCountNotification($orgStockMovement);

        $subscribers = User::where('group_id', $orgStockMovement->group_id)
            ->where('status', true)
            ->whereJsonLength('settings->notifications->'.$event->value, '>', 0)
            ->get()
            ->filter(fn (User $user) => $user->authTo([
                "inventory.$orgStockMovement->organisation_id.view",
                "accounting.$orgStockMovement->organisation_id.view",
                "stocks.$orgStockMovement->warehouse_id.view",
                "supervisor-stocks.$orgStockMovement->warehouse_id.view",
            ]));

        foreach ($subscribers as $subscriber) {
            NotifyUserOnChosenChannels::run($subscriber, $event, clone $notification, $notification->title(), $notification->body(), $notification->url());
        }

        return $subscribers->count();
    }
}
