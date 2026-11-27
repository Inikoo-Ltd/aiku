<?php

/*
 * Author Louis Perez
 * Created on 18-11-2026-08h-52m
 * GitHub: https://github.com/louis-perez
 * Copyright 2026
*/

namespace App\Enums\Goods\ProductDevelopment;

use App\Enums\EnumHelperTrait;

enum ProductDevelopmentCopySectionEnum: string
{
    use EnumHelperTrait;

    case SPECIFICATIONS = 'specifications';
    case INGREDIENTS    = 'ingredients';
    case COMPLIANCE     = 'compliance';
    case DESCRIPTIONS   = 'descriptions';
    case FORMULATION    = 'formulation';

    public static function labels(): array
    {
        return [
            'specifications' => __('Weight, size and origin'),
            'ingredients'    => __('Ingredients and materials'),
            'compliance'     => __('Compliance and documents'),
            'descriptions'   => __('Descriptions'),
            'formulation'    => __('Formulation'),
        ];
    }

    public static function descriptions(): array
    {
        return [
            'specifications' => __('Net and gross weight, volume, dimensions, country of origin'),
            'ingredients'    => __('Ingredients and material composition'),
            'compliance'     => __('Tariff code, CPNP/UFI/SCPN, UN and GPSR details, pictograms, label marks and the shared documents'),
            'descriptions'   => __('Description texts and their translations'),
            'formulation'    => __('The recipe of the product in this factory'),
        ];
    }

    /**
     * @return array<int, self>
     */
    public static function forKind(ProductDevelopmentKindEnum $kind): array
    {
        return array_values(array_filter(self::cases(), fn (self $section) => $section !== self::FORMULATION || $kind === ProductDevelopmentKindEnum::RECIPE));
    }

    /**
     * The trade unit columns the section copies as they are.
     *
     * @return array<int, string>
     */
    public function columns(): array
    {
        return match ($this) {
            self::SPECIFICATIONS => ['net_weight', 'gross_weight', 'marketing_weight', 'net_volume', 'volume', 'marketing_dimensions', 'type', 'is_divisible', 'origin_country_id', 'country_of_origin'],
            self::INGREDIENTS => ['marketing_ingredients'],
            self::COMPLIANCE => [
                'tariff_code', 'duty_rate', 'hts_us', 'cpnp_number', 'ufi_number', 'scpn_number',
                'un_number', 'un_class', 'packing_group', 'proper_shipping_name', 'hazard_identification_number',
                'gpsr_manufacturer', 'gpsr_eu_responsible', 'gpsr_warnings', 'gpsr_manual', 'gpsr_class_category_danger', 'gpsr_class_languages',
                ...array_keys(ProductDevelopmentTargetFieldEnum::pictograms()),
                'label_info',
            ],
            self::DESCRIPTIONS => ['description', 'description_title', 'description_extra', 'description_i8n', 'description_title_i8n', 'description_extra_i8n'],
            self::FORMULATION => [],
        };
    }
}
