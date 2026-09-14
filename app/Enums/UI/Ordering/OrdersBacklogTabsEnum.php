<?php

/*
 * author Arya Permana - Kirin
 * created on 13-12-2024-10h-51m
 * github: https://github.com/KirinZero0
 * copyright 2024
*/

namespace App\Enums\UI\Ordering;

use App\Enums\EnumHelperTrait;
use App\Enums\HasTabs;
use App\Enums\Ordering\Order\OrderStateEnum;

enum OrdersBacklogTabsEnum: string
{
    use EnumHelperTrait;
    use HasTabs;

    case IN_BASKET = 'in_basket';
    case SUBMITTED_PAID = 'submitted_paid';
    case SUBMITTED_UNPAID = 'submitted_unpaid';


    case IN_WAREHOUSE = 'in_warehouse'; // Waiting to be picking
    case HANDLING = 'handling';  // Being picking
    case HANDLING_BLOCKED = 'handling_blocked';  // Being picking

    case PICKED = 'picked';
    case PACKING = 'packing';
    case PACKED = 'packed';
    case FINALISED = 'finalised';  // Invoiced and ready to be dispatched


    case DISPATCHED_TODAY = 'dispatched_today';

    case RETURNED = 'returned';

    /**
     * The order states these tabs put in front of staff. Anything live and outside this list is in
     * no queue at all, which is how an order goes missing (HELP-3116), so MonitorOrdersInLimbo
     * counts the difference and says so on Discord rather than letting it sit there quietly.
     *
     * Creating is the customer's own basket, cancelled and dispatched are done with: none of them
     * is work waiting on us.
     */
    public static function statesShown(): array
    {
        return [
            OrderStateEnum::SUBMITTED,
            OrderStateEnum::IN_WAREHOUSE,
            OrderStateEnum::HANDLING,
            OrderStateEnum::HANDLING_BLOCKED,
            OrderStateEnum::PICKED,
            OrderStateEnum::PACKING,
            OrderStateEnum::PACKED,
            OrderStateEnum::FINALISED,
        ];
    }

    public static function statesNeedingNoQueue(): array
    {
        return [
            OrderStateEnum::CREATING,
            OrderStateEnum::CANCELLED,
            OrderStateEnum::DISPATCHED,
        ];
    }

}
