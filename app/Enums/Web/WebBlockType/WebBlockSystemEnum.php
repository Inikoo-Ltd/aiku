<?php

/*
 * author Louis Perez
 * created on 24-02-2026-09h-49m
 * github: https://github.com/louis-perez
 * copyright 2026
*/

namespace App\Enums\Web\WebBlockType;

use App\Enums\EnumHelperTrait;

enum WebBlockSystemEnum: string
{
    use EnumHelperTrait;

    // DO NOT CHANGE. THIS IS FRAGILE (Had to follow previous setup)
    // key -> value (name from templates json)
    case COLLECTIONS = 'Collections-1';
    case SUB_DEPARTMENTS  = 'Sub-Departments-1';
    case PRODUCTS  = 'Product showcase A';
    case LIST_PRODUCTS  = 'List products';
    case FAMILY  = 'family';
    case FAMILIES  = 'families';

    // ONLY CHANGE ARRAY LIST UNDER TEMPLATE CODES PLEASE :)
    public function webBlockSlugs(): array
    {
        return match ($this) {
            self::SUB_DEPARTMENTS => [
                'sub-departments-1',
                'sub-departments-2',
            ],

            self::FAMILIES => [
                'families-1',
                'families-2',
                'families-3',
            ],

            self::LIST_PRODUCTS => [
                'products-1',
                'products-2',
            ],
        };
    }
}
