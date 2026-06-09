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
    case PRODUCT    = 'Product';
    case FAMILIES  = 'Family';

    case DEPARTMENT_DESCRIPTION = 'DepartmentDescription';

    case FAMILY_DESCRIPTION = 'FamilyDescription';
    case FAMILY_OVERVIEW = 'FamiliesOverview';

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

            self::PRODUCT => [
                'product-1',
                'product-2'
            ],

            self::FAMILY_DESCRIPTION => [
                'department-description-1',
                'department-description-2',
            ],

            self::FAMILY_DESCRIPTION => [
                'family-1',
                'family-2',
                'family-2-extra-description',
                'family-3',
                'family-3-extra-description',
            ],

            self::FAMILY_OVERVIEW => [
                'families-1-overview',
            ],
        };
    }

    public static function allTemplateCodes(): array
    {
        return array_merge(
            ...array_map(fn ($item) => $item->templateCodes(), self::cases())
        );
    }
}
