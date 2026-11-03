<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 15 Oct 2026 Mijas, Spain
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Procurement\PurchaseOrder;

use App\Actions\SysAdmin\User\NotifyUserOnChosenChannels;
use App\Enums\SysAdmin\User\UserNotificationEnum;
use App\Models\Procurement\PurchaseOrder;
use App\Models\SysAdmin\User;
use App\Notifications\PurchaseOrderStageChangedByAgentNotification;
use Closure;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * When an agent's staff, not the buying organisation, changes a stage of an order, its buyer is told.
 * Everything is written in the buyer's language, not the agent's: $change is a closure so it is translated there too.
 */
class NotifyBuyerOfAgentStageChange
{
    use AsAction;

    /**
     * @param Closure(): string $change
     */
    public function handle(PurchaseOrder $purchaseOrder, ?User $user, Closure $change): bool
    {
        $buyer = $purchaseOrder->buyer;

        if (!$user || !$buyer || !$buyer->status || $buyer->id === $user->id
            || !$purchaseOrder->isAgentOrder() || $user->authTo("procurement.$purchaseOrder->organisation_id.edit")) {
            return false;
        }

        $previousLocale = app()->getLocale();
        app()->setLocale($buyer->language?->code ?: 'en');

        try {
            $notification = new PurchaseOrderStageChangedByAgentNotification($purchaseOrder, $user->contact_name ?: $user->username, $change());
            NotifyUserOnChosenChannels::run(
                $buyer,
                UserNotificationEnum::PURCHASE_ORDER_STAGE_BY_AGENT,
                $notification,
                __(':reference: :change', ['reference' => $purchaseOrder->reference, 'change' => $notification->change]),
                __(':user, from the agent, recorded it on the order.', ['user' => $notification->agentUserName]),
                route('grp.org.procurement.purchase_orders.show', [$purchaseOrder->organisation->slug, $purchaseOrder->slug])
            );
        } finally {
            app()->setLocale($previousLocale);
        }

        return true;
    }
}
