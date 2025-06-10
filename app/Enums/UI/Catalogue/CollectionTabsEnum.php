<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 11 May 2024 20:50:14 British Summer Time, Sheffield, UK
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Enums\UI\Catalogue;

use App\Enums\EnumHelperTrait;
use App\Enums\HasTabsWithQuantity;
use App\Models\Catalogue\Collection;

enum CollectionTabsEnum: string
{
    use EnumHelperTrait;
    use HasTabsWithQuantity;



    case SHOWCASE            = 'showcase';

    // case DEPARTMENTS         = 'departments';
    case FAMILIES            = 'families';
    case PRODUCTS            = 'products';
    // case COLLECTIONS         = 'collections';
    // case SALES               = 'sales';
    // case CUSTOMERS           = 'customers';
    // case OFFERS              = 'offers';
    // case MAILSHOTS           = 'mailshots';
    // case RELATED_CATEGORIES  = 'related_categories';

    case HISTORY             = 'history';

    // case DATA                = 'data';
    // case IMAGES              = 'images';



    public function blueprint(Collection $parent): array
    {
        return match ($this) {
            // DepartmentTabsEnum::DATA => [
            //     'title' => __('database'),
            //     'icon'  => 'fal fa-database',
            //     'type'  => 'icon',
            //     'align' => 'right',
            // ],
            // DepartmentTabsEnum::PRODUCTS => [
            //     'title' => __('products'),
            //     'icon'  => 'fal fa-cube',
            // ],
            // DepartmentTabsEnum::FAMILIES => [
            //     'title' => __('families'),
            //     'icon'  => 'fal fa-cubes',
            // ],
            // DepartmentTabsEnum::SALES => [
            //     'title' => __('sales'),
            //     'icon'  => 'fal fa-money-bill-wave',
            // ],
            // DepartmentTabsEnum::CUSTOMERS => [
            //     'title' => __('customers'),
            //     'icon'  => 'fal fa-user',
            // ],DepartmentTabsEnum::OFFERS => [
            //     'title' => __('offers'),
            //     'icon'  => 'fal fa-tags',
            // ],DepartmentTabsEnum::MAILSHOTS => [
            //     'title' => __('mailshots'),
            //     'icon'  => 'fal fa-bullhorn',
            // ],DepartmentTabsEnum::RELATED_CATEGORIES => [
            //     'title' => __('related categories'),
            //     'icon'  => 'fal fa-project-diagram',
            // ],DepartmentTabsEnum::IMAGES=> [
            //     'title' => __('images'),
            //     'icon'  => 'fal fa-camera-retro',
            //     'type'  => 'icon',
            //     'align' => 'right',
            // ],
            CollectionTabsEnum::HISTORY => [
                'title' => __('history'),
                'icon'  => 'fal fa-clock',
                'type'  => 'icon',
                'align' => 'right',
            ],
            CollectionTabsEnum::SHOWCASE => [
                'title' => __('Details'),
                'icon'  => 'fas fa-info-circle',
            ],
            // CollectionTabsEnum::DEPARTMENTS => [
            //     'title' => __("Departments")." ({$parent->stats->number_departments})",
            //     'icon'  => 'fal fa-folder-tree',
            // ],
            CollectionTabsEnum::FAMILIES => [
                'title' => __('Families')." ({$parent->stats->number_families})",
                'icon'  => 'fal fa-folder',
            ],
            CollectionTabsEnum::PRODUCTS => [
                'title' => __('Products')." ({$parent->stats->number_products})",
                'icon'  => 'fal fa-cube',
            ],
            // CollectionTabsEnum::COLLECTIONS => [
            //     'title' => __('Collections')." ({$parent->stats->number_collections})",
            //     'icon'  => 'fal fa-cube',
            // ],
        };
    }
}
