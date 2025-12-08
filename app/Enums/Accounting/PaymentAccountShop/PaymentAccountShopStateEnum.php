<?php

/*
 * author Arya Permana - Kirin
 * created on 17-02-2025-15h-23m
 * github: https://github.com/KirinZero0
 * copyright 2025
*/

namespace App\Enums\Accounting\PaymentAccountShop;

use App\Enums\EnumHelperTrait;

enum PaymentAccountShopStateEnum: string
{
    use EnumHelperTrait;

    case IN_PROCESS = 'in_process';
    case ACTIVE = 'active';
    case INACTIVE = 'inactive';

    public function label(): string
    {
        return match ($this) {
            self::IN_PROCESS => __('In Process'),
            self::ACTIVE => __('Active'),
            self::INACTIVE => __('Inactive'),
        };
    }

    public function stateIcon(): array
    {
        return match ($this) {
            self::IN_PROCESS => [
                'tooltip' => __('In Process'),
                'icon' => 'fal fa-seedling',
            ],
            self::ACTIVE => [
                'tooltip' => __('Active'),
                'icon' => 'fas fa-check-circle',
                'class' => 'text-green-500',
            ],
            self::INACTIVE => [
                'tooltip' => __('Inactive'),
                'icon' => 'fas fa-times-circle',
                'class' => 'text-red-500',
            ],
        };
    }
}
