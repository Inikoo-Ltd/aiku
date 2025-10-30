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

enum OrdersBacklogTabsEnum: string
{
    use EnumHelperTrait;
    use HasTabs;

    case IN_BASKET = 'in_basket';
    case SUBMITTED_PAID = 'submitted_paid';
    case SUBMITTED_UNPAID = 'submitted_unpaid';


    case IN_WAREHOUSE = 'in_warehouse'; // Waiting to be picked
    case HANDLING = 'handling';  // Being picked
    case HANDLING_BLOCKED = 'handling_blocked';  // Being picked

    case PACKED = 'packed';
    case FINALISED = 'finalised';  // Invoiced and ready to be dispatched


    case DISPATCHED_TODAY = 'dispatched_today';




}
