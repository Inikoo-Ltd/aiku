<?php

/*
 * Author Louis Perez
 * Created on 26-10-2026-10h-00m
 * GitHub: https://github.com/louis-perez
 * Copyright 2026
*/

namespace App\Enums\Goods\ProductDevelopment;

use App\Enums\EnumHelperTrait;

/**
 * What is being developed: a product bought from a supplier, or a recipe made in our own production.
 */
enum ProductDevelopmentKindEnum: string
{
    use EnumHelperTrait;

    case PRODUCT = 'product';
    case RECIPE  = 'recipe';

    public static function labels(): array
    {
        return [
            'product' => __('Sourced product'),
            'recipe'  => __('In-house production'),
        ];
    }

    public static function descriptions(): array
    {
        return [
            'product' => __('Bought from a supplier: specs, compliance, packaging and artwork'),
            'recipe'  => __('Made in our own production: recipe, formulation and trial runs'),
        ];
    }

    public function icon(): string
    {
        return match ($this) {
            self::PRODUCT => 'fal fa-box-open',
            self::RECIPE => 'fal fa-flask',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::PRODUCT => 'sky',
            self::RECIPE => 'amber',
        };
    }
}
