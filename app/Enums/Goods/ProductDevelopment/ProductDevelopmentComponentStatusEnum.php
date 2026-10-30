<?php

/*
 * Author Louis Perez
 * Created on 26-10-2026-16h-30m
 * GitHub: https://github.com/louis-perez
 * Copyright 2026
*/

namespace App\Enums\Goods\ProductDevelopment;

use App\Enums\EnumHelperTrait;

enum ProductDevelopmentComponentStatusEnum: string
{
    use EnumHelperTrait;

    case PENDING  = 'pending';
    case APPROVED = 'approved';

    public static function labels(): array
    {
        return [
            'pending'  => __('Pending approval'),
            'approved' => __('Approved for purchasing'),
        ];
    }

    public function icon(): string
    {
        return match ($this) {
            self::PENDING => 'fal fa-clock',
            self::APPROVED => 'fal fa-check-circle',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::PENDING => 'amber',
            self::APPROVED => 'green',
        };
    }
}
