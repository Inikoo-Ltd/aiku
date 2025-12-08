<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 15 Jun 2024 01:03:06 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Enums\Fulfilment\RentalAgreementClause;

use App\Enums\Catalogue\Shop\ShopTypeEnum;
use App\Enums\EnumHelperTrait;
use App\Models\Catalogue\ProductCategory;
use App\Models\Catalogue\Shop;
use App\Models\SysAdmin\Organisation;

enum RentalAgreementClauseTypeEnum: string
{
    use EnumHelperTrait;

    case PRODUCT = 'product';
    case SERVICE = 'service';
    case RENTAL = 'rental';

    public static function labels(Shop|Organisation|ProductCategory|null $parent = null): array
    {
        return [
            'product' => __('Product'),
            'service' => __('Services'),
            'rental' => __('Rentals'),
        ];

    }

    public static function typeIcon(): array
    {
        return [
            'product' => [
                'tooltip' => __('Physical good'),
                'icon' => 'fal fa-cube',
                'app' => [
                    'name' => 'cube',
                    'type' => 'font-awesome-5',
                ],
            ],
            'service' => [
                'tooltip' => __('Service'),
                'icon' => 'fal fa-concierge-bell',
                'app' => [
                    'name' => 'concierge-bell',
                    'type' => 'font-awesome-5',
                ],
            ],
            'rental' => [
                'tooltip' => __('Rental'),
                'icon' => 'fal fa-garage',
                'app' => [
                    'name' => 'garage',
                    'type' => 'font-awesome-5',
                ],
            ],
        ];
    }

    public static function count(Shop|Organisation|ProductCategory $parent): array
    {
        $stats = $parent->stats;
        $counts = [
            'product' => $stats->number_rental_agreement_clause_type_products,
            'service' => $stats->number_rental_agreement_clause_type_service,
            'rental' => $stats->number_rental_agreement_clause_type_rental,
        ];

        if ($parent instanceof Shop) {
            unset($counts['subscription']);
            if ($parent->type != ShopTypeEnum::FULFILMENT) {
                unset($counts['rental']);
            }
        }

        return $counts;
    }
}
