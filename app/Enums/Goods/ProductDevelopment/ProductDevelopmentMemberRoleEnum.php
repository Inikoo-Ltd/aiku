<?php

/*
 * Author Louis Perez
 * Created on 15-10-2026-15h-20m
 * GitHub: https://github.com/louis-perez
 * Copyright 2026
*/

namespace App\Enums\Goods\ProductDevelopment;

use App\Enums\EnumHelperTrait;

enum ProductDevelopmentMemberRoleEnum: string
{
    use EnumHelperTrait;

    case BUYER      = 'buyer';
    case AGENT      = 'agent';
    case PURCHASING = 'purchasing';
    case COMPLIANCE = 'compliance';
    case DESIGNER   = 'designer';
    case WEBMASTER  = 'webmaster';
    case APPROVER   = 'approver';
    case PRODUCTION = 'production';

    public static function labels(): array
    {
        return [
            'buyer'      => __('Buyer'),
            'agent'      => __('Agent'),
            'purchasing' => __('Purchasing'),
            'compliance' => __('Compliance'),
            'designer'   => __('Designer'),
            'webmaster'  => __('Webmaster'),
            'approver'   => __('Approver'),
            'production' => __('Production'),
        ];
    }

    public function icon(): string
    {
        return match ($this) {
            self::BUYER => 'fal fa-shopping-bag',
            self::AGENT => 'fal fa-user-tie',
            self::PURCHASING => 'fal fa-file-invoice-dollar',
            self::COMPLIANCE => 'fal fa-shield-check',
            self::DESIGNER => 'fal fa-pencil-ruler',
            self::WEBMASTER => 'fal fa-globe',
            self::APPROVER => 'fal fa-stamp',
            self::PRODUCTION => 'fal fa-flask',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::BUYER => 'blue',
            self::AGENT => 'sky',
            self::PURCHASING => 'orange',
            self::COMPLIANCE => 'green',
            self::DESIGNER => 'purple',
            self::WEBMASTER => 'cyan',
            self::APPROVER => 'rose',
            self::PRODUCTION => 'amber',
        };
    }

    public function statsColumn(): string
    {
        return 'number_required_missing_'.$this->value;
    }
}
