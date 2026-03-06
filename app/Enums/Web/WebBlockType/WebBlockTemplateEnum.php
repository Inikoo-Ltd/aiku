<?php

/*
 * author Louis Perez
 * created on 24-02-2026-09h-49m
 * github: https://github.com/louis-perez
 * copyright 2026
*/

namespace App\Enums\Web\WebBlockType;

use App\Enums\EnumHelperTrait;

enum WebBlockTemplateEnum: string
{
    use EnumHelperTrait;

    // DO NOT CHANGE. THIS IS USED ON REPAIR FILES. VALUE MUST BE THE SAME AS FUNCTION CALL ON WEBSITE ['live{Value}Snapshot', 'unpublished{Value}Snapshot']
    case SUB_DEPARTMENTS  = 'SubDepartment';
    case LIST_PRODUCTS  = 'Products';
    case FAMILIES  = 'Family';

    // ONLY CHANGE ARRAY LIST UNDER TEMPLATE CODES PLEASE :)
    public function templateCodes(): array
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
