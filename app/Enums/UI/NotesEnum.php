<?php

/*
 * Author Louis Perez
 * Created on 27-08-2026-12h-03m
 * GitHub: https://github.com/louis-perez
 * Copyright 2026
*/

namespace App\Enums\UI;

use Illuminate\Support\Arr;

enum NotesEnum: string
{
    case SHIPPING_LABEL     = 'shipping';
    case CUSTOMER           = 'customer';
    case PUBLIC             = 'public';
    case INTERNAL           = 'internal';
    case WAREHOUSE          = 'warehouse';
    case CREDIT_TRANSACTION = 'credit_transaction';

    
    public function label(): string
    {
        return match ($this) {
            self::SHIPPING_LABEL        => __("Shippings Label (From Customer)"),
            self::CUSTOMER              => __("Customers Note"),
            self::PUBLIC                => __("Public Note"),
            self::INTERNAL              => __("CRMs Note (Private)"),
            self::WAREHOUSE             => __("Warehouse Note (Private)"),
            self::CREDIT_TRANSACTION    => __("Credit Transaction Note"),
        };
    }

    public function boilerPlate(array $excludedKeys = ['textColor']): array
    {
        return Arr::except(match ($this) {
            self::SHIPPING_LABEL        => [
                "bgColor"       => "#93C5FD",
                "textColor"     => "#93C5FD",
            ],
            self::CUSTOMER              => [
                "bgColor"       => "#599FF0",
                "textColor"     => "#599FF0",
            ],
            self::PUBLIC                => [
                "bgColor"       => "#AAAAAA",
                "textColor"     => "#AAAAAA",
            ],
            self::INTERNAL              => [
                "bgColor"       => "#D8B4FE",
                "textColor"     => "#D8B4FE",
            ],
            self::WAREHOUSE             => [
                "bgColor"       => "#FCD34D",
                "textColor"     => "#FCD34D",
            ],
            self::CREDIT_TRANSACTION    => [
                "bgColor"       => "#B873F5",
                "textColor"     => "#B873F5",
            ],
        },  $excludedKeys);
    }
}
