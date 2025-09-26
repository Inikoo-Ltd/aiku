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
    case PICKING = 'picking';
    case BLOCKED = 'blocked';
    case PACKED = 'packed';
    case PACKED_DONE = 'packed_done';
    case DISPATCHED_TODAY = 'dispatched_today';





    // public function blueprint(): array
    // {
    //     return match ($this) {

    //         OrderTabsEnum::TRANSACTIONS => [
    //             'title' => __('Transactions'),
    //             'icon'  => 'fal fa-bars',
    //         ],
    //         // OrderTabsEnum::PAYMENTS => [
    //         //     'type'  => 'icon',
    //         //     'align' => 'right',
    //         //     'title' => __('Payments'),
    //         //     'icon'  => 'fal fa-dollar-sign',
    //         // ],

    //         // OrderTabsEnum::SENT_EMAILS => [
    //         //     'title' => __('Sent emails'),
    //         //     'icon'  => 'fal fa-envelope',
    //         //     'type'  => 'icon',
    //         //     'align' => 'right',

    //         // ],
    //         // OrderTabsEnum::DISCOUNTS => [
    //         //     'title' => __('Discounts'),
    //         //     'icon'  => 'fal fa-tag',
    //         //     'type'  => 'icon',
    //         //     'align' => 'right',

    //         // ],
    //         OrderTabsEnum::INVOICES => [
    //             'title' => __('Invoices'),
    //             'icon'  => 'fal fa-file-invoice-dollar',
    //             'type'  => 'icon',
    //             'align' => 'right',

    //         ],
    //         OrderTabsEnum::DELIVERY_NOTES => [
    //             'title' => __('Delivery notes'),
    //             'icon'  => 'fal fa-truck',
    //             'type'  => 'icon',
    //             'align' => 'right',
    //         ],
    //         OrderTabsEnum::ATTACHMENTS => [
    //             'title' => __('Attachments'),
    //             'icon'  => 'fal fa-paperclip',
    //             'type'  => 'icon',
    //             'align' => 'right',
    //         ],
    //         //OrderTabsEnum::HISTORY => [
    //         //     'title' => __('History'),
    //         //     'icon'  => 'fal fa-clock',
    //         //     'type'  => 'icon',
    //         //     'align' => 'right',
    //         // ],

    //     };
    // }
}
