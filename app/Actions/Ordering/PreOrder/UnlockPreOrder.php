<?php

/*
 * Author Louis Perez
 * Created on 29-09-2026-10h-53m
 * GitHub: https://github.com/louis-perez
 * Copyright 2026
*/

namespace App\Actions\Ordering\PreOrder;

use App\Enums\Ordering\PreOrder\PreOrderStateEnum;
use App\Models\Ordering\PreOrder;
use App\Models\SysAdmin\User;
use Illuminate\Support\Facades\Event;
use Lorisleiva\Actions\Concerns\AsObject;
use OwenIt\Auditing\Events\AuditCustom;

/**
 * Opens a locked pre-order to one member of staff for a short while so they can change its lines,
 * charges or addresses, or locks it again early. Both are written to the order's history (HELP-3432).
 */
class UnlockPreOrder
{
    use AsObject;

    public const MINUTES = 15;

    public function handle(PreOrder $preOrder, User $user, bool $unlock = true): PreOrder
    {
        $data = $preOrder->data ?? [];

        if ($unlock) {
            $data['unlock'] = [
                'user_id' => $user->id,
                'until'   => now()->addMinutes(self::MINUTES)->toIso8601String(),
            ];
        } else {
            unset($data['unlock']);
        }

        $preOrder->update(['data' => $data]);

        if (!$unlock && in_array($preOrder->state, PreOrderStateEnum::holdingStock())) {
            HydratePreOrderReservedStock::run(HydratePreOrderReservedStock::make()->orgStockIds($preOrder));
        }

        $order                 = $preOrder->order;
        $order->auditEvent     = $unlock ? 'pre_order_unlocked' : 'pre_order_locked';
        $order->isCustomEvent  = true;
        $order->auditCustomOld = [];
        $order->auditCustomNew = $unlock
            ? ['pre_order' => __('Unlocked for :minutes minutes by :user', ['minutes' => self::MINUTES, 'user' => $user->contact_name ?: $user->username])]
            : ['pre_order' => __('Locked again by :user', ['user' => $user->contact_name ?: $user->username])];
        Event::dispatch(new AuditCustom($order));

        return $preOrder;
    }
}
