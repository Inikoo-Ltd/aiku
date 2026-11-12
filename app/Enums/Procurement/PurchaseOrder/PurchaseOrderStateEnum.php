<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 17 Apr 2023 17:11:07 Malaysia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Enums\Procurement\PurchaseOrder;

use App\Enums\EnumHelperTrait;
use App\Enums\Procurement\PurchaseOrderTransaction\PurchaseOrderTransactionStateEnum;

/**
 * Where a purchase order is, the one place it is kept. A person drafts, submits, agrees, cancels or marks it not
 * received; goods-in settles it; from Agreed to Homeward Bound the state follows the order's stages
 * (PurchaseOrderHydrateState), so it moves forward, and back, as production, the agent warehouse and the deliveries do.
 */
enum PurchaseOrderStateEnum: string
{
    use EnumHelperTrait;

    case IN_PROCESS = 'in_process';
    case SUBMITTED = 'submitted';
    case CONFIRMED = 'confirmed';
    case IN_PRODUCTION = 'in_production';
    case IN_WAREHOUSE = 'in_warehouse';
    case READY = 'ready';
    case HANDED_OVER = 'handed_over';
    case ARRIVED = 'arrived';
    // Final
    case SETTLED = 'settled';
    case CANCELLED = 'cancelled';
    case NOT_RECEIVED = 'not_received';

    /**
     * Sent to the supplier and not finished: submitted, agreed and every step until the goods are booked in.
     *
     * @return array<int, self>
     */
    public static function open(): array
    {
        return [self::SUBMITTED, ...self::agreed()];
    }

    /**
     * Agreed with the supplier and not finished. Between these the state follows the order's stages.
     *
     * @return array<int, self>
     */
    public static function agreed(): array
    {
        return [self::CONFIRMED, self::IN_PRODUCTION, self::IN_WAREHOUSE, self::READY, self::HANDED_OVER, self::ARRIVED];
    }

    /**
     * Not finished: being drafted or open.
     *
     * @return array<int, self>
     */
    public static function live(): array
    {
        return [self::IN_PROCESS, ...self::open()];
    }

    /**
     * @return array<int, string>
     */
    public static function openValues(): array
    {
        return array_column(self::open(), 'value');
    }

    /**
     * @return array<int, string>
     */
    public static function agreedValues(): array
    {
        return array_column(self::agreed(), 'value');
    }

    /**
     * @return array<int, string>
     */
    public static function liveValues(): array
    {
        return array_column(self::live(), 'value');
    }

    public static function openSql(): string
    {
        return "'".implode("', '", self::openValues())."'";
    }

    public static function agreedSql(): string
    {
        return "'".implode("', '", self::agreedValues())."'";
    }

    public static function liveSql(): string
    {
        return "'".implode("', '", self::liveValues())."'";
    }

    public function isOpen(): bool
    {
        return in_array($this, self::open(), true);
    }

    public function isAgreed(): bool
    {
        return in_array($this, self::agreed(), true);
    }

    public function isLive(): bool
    {
        return in_array($this, self::live(), true);
    }

    /**
     * The state the order's lines carry: lines stop at agreed, whatever step the order reached after it.
     */
    public function transactionState(): PurchaseOrderTransactionStateEnum
    {
        return $this->isAgreed() ? PurchaseOrderTransactionStateEnum::CONFIRMED : PurchaseOrderTransactionStateEnum::from($this->value);
    }

    public static function labels(): array
    {
        return [
            'in_process'    => __('In process'),
            'submitted'     => __('Submitted'),
            'confirmed'     => __('Agreed'),
            'in_production' => __('Making It Happen'),
            'in_warehouse'  => __('In warehouse'),
            'ready'         => __('Ready to Roll'),
            'handed_over'   => __('Clean Hand Over'),
            'arrived'       => __('Homeward Bound'),
            'settled'       => __('Counted & Costed'),
            'cancelled'     => __('Cancelled'),
            'not_received'  => __('Not Received'),
        ];
    }

    public static function stateIcon(): array
    {
        return [
            'in_process'   => [
                'tooltip' => __('Creating'),
                'icon'    => 'fal fa-seedling',
                'class'   => 'text-lime-500',
                'color'   => 'lime',
                'app'     => [
                    'name' => 'seedling',
                    'type' => 'font-awesome-5',
                ],
            ],
            'submitted'    => [
                'tooltip' => __('Submitted'),
                'icon'    => 'fal fa-paper-plane',
                'class'   => 'text-indigo-400',
                'color'   => 'indigo',
                'app'     => [
                    'name' => 'share',
                    'type' => 'font-awesome-5',
                ],
            ],
            'confirmed'    => [
                'tooltip' => __('Terms agreed with the supplier'),
                'icon'    => 'fal fa-check-circle',
                'class'   => 'text-gray-500',
                'color'   => 'gray',
                'app'     => [
                    'name' => 'spell-check',
                    'type' => 'font-awesome-5',
                ],
            ],
            'in_production' => [
                'tooltip' => __('In production at the supplier'),
                'icon'    => 'fal fa-cogs',
                'class'   => 'text-amber-500',
                'color'   => 'amber',
                'app'     => [
                    'name' => 'cogs',
                    'type' => 'font-awesome-5',
                ],
            ],
            'in_warehouse'  => [
                'tooltip' => __('Goods arrived at the agent warehouse'),
                'icon'    => 'fal fa-boxes',
                'class'   => 'text-amber-600',
                'color'   => 'amber',
                'app'     => [
                    'name' => 'boxes',
                    'type' => 'font-awesome-5',
                ],
            ],
            'ready'         => [
                'tooltip' => __('Final checks done'),
                'icon'    => 'fal fa-clipboard-check',
                'class'   => 'text-teal-500',
                'color'   => 'teal',
                'app'     => [
                    'name' => 'clipboard-check',
                    'type' => 'font-awesome-5',
                ],
            ],
            'handed_over'   => [
                'tooltip' => __('Available to ship'),
                'icon'    => 'fal fa-hands-helping',
                'class'   => 'text-teal-600',
                'color'   => 'teal',
                'app'     => [
                    'name' => 'hands-helping',
                    'type' => 'font-awesome-5',
                ],
            ],
            'arrived'       => [
                'tooltip' => __('Transit & arrival'),
                'icon'    => 'fal fa-truck-loading',
                'class'   => 'text-sky-500',
                'color'   => 'sky',
                'app'     => [
                    'name' => 'truck-loading',
                    'type' => 'font-awesome-5',
                ],
            ],
            'settled'      => [
                'tooltip' => __('Booked into stock'),
                'icon'    => 'fal fa-check-double',
                'class'   => 'text-success-500',
                'color'   => 'success',
                'app'     => [
                    'name' => 'check-double',
                    'type' => 'font-awesome-5',
                ],
            ],
            'cancelled'    => [
                'tooltip' => __('Cancelled'),
                'icon'    => 'fal fa-times-circle',
                'class'   => 'text-red-500',
                'color'   => 'danger',
                'app'     => [
                    'name' => 'times-circle',
                    'type' => 'font-awesome-5',
                ],
            ],
            'not_received' => [
                'tooltip' => __('Not Received'),
                'icon'    => 'fal fa-exclamation-circle',
                'class'   => 'text-warning-500',
                'color'   => 'warning',
                'app'     => [
                    'name' => 'exclamation-circle',
                    'type' => 'font-awesome-5',
                ],
            ],
        ];
    }
}
