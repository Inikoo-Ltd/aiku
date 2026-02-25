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

    //list
    case COLLECTIONS = 'Collections-1';
    case SUB_DEPARTMENTS  = 'Sub-Departments-1';
    case PRODUCTS  = 'Product showcase A';
    case LIST_PRODUCTS  = 'List products';
    case FAMILIES  = 'families';

    //description
    case DEPARTMENT  = 'department-description';
    case SUB_DEPARTMENT  = 'sub-department-description';
    case FAMILY  = 'family';
    case COLLECTION  = 'collection-description-1';

    // ONLY CHANGE ARRAY LIST UNDER TEMPLATE CODES PLEASE :)
    public function webBlockSlugs(): array
    {
        return match ($this) {
            //list
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
            
            self::PRODUCTS => [
                'product-1',
                'product-2'
            ],

            //description
            self::DEPARTMENT => [
               'department-description-1',
            ],

            self::SUB_DEPARTMENT => [
               'sub-department-description-1',
            ],

            self::FAMILY => [
               'family-1',
            ],

             self::COLLECTION => [
               'collection-description-1',
            ],
        };
    }
}
