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

    // To handle RepairProhibitedWebBlocksInNonModelWebpage dynamically. Prevent non-catalogue webpage from having any of the webBlockType listed below
    case CATALOGUE_EXCLUSIVES = 'CatalogueExclusives';

    // ONLY MODIFY THE ARRAYS INSIDE templateCodes(). DO NOT CHANGE ENUM VALUES.
    public function templateCodes(): array
    {
        return match ($this) {
            self::SUB_DEPARTMENTS => [
                'sub-departments-1',
                'sub-departments-2',
                'sub-departments-3',
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

            self::DEPARTMENT_DESCRIPTION => [
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

            // Web Block Codes that are exclusive to certain ModelType webpages (Family/Sub-Department/Department/Product/Collection webpages)
            self::CATALOGUE_EXCLUSIVES => [
                'relatedProductCategory',
                'recommendation-product-category-from-master',
                'recommendation-from-master',
                'recommendation-customer-recently-bought-1',
                'luigi-trends-1',
                'luigi-last-seen-1',
                'luigi-item-alternatives-1',
                'see-also-1',
                'top-families',
                'faq-department',
                'products',
                'product',
                'family',
                'sub-department-1',
                'departments',
                'collections-1',
                'collection-description-1',
                'collection-1',
            ],
        };
    }

    public static function allTemplateCodes(): array
    {
        return array_merge(
            ...array_map(fn ($item) => $item->templateCodes(), self::cases())
        );
    }

    public static function allTemplateCodesFiltered(): array
    {
        return array_merge(
            ...array_map(
                fn ($item) => $item->templateCodes(),
                array_filter(
                    self::cases(),
                    fn (self $item) => $item !== self::CATALOGUE_EXCLUSIVES
                )
            )
        );
    }
}
