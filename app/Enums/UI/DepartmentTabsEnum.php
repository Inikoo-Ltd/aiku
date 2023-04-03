<?php
/*
 * Author: Jonathan Lopez Sanchez <jonathan@ancientwisdom.biz>
 * Created: Mon, 20 Mar 2023 14:46:07 Central European Standard Time, Malaga, Spain
 * Copyright (c) 2023, Inikoo LTD
 */

namespace App\Enums\UI;

use App\Enums\EnumHelperTrait;
use App\Enums\HasTabs;

enum DepartmentTabsEnum: string
{
    use EnumHelperTrait;
    use HasTabs;



    case SHOWCASE            = 'showcase';

    case FAMILIES            = 'families';
    case PRODUCTS            = 'products';

    case SALES               = 'sales';
    case CUSTOMERS           = 'customers';
    case OFFERS              = 'offers';
    case MAILSHOTS           = 'mailshots';
    case RELATED_CATEGORIES  = 'related_categories';

    case HISTORY             = 'history';

    case DATA                = 'data';
    case IMAGES              = 'images';



    public function blueprint(): array
    {
        return match ($this) {
            DepartmentTabsEnum::DATA => [
                'title' => __('database'),
                'icon'  => 'fal fa-database',
                'type'  => 'icon',
                'align' => 'right',
            ],
            DepartmentTabsEnum::PRODUCTS => [
                'title' => __('products'),
                'icon'  => 'fal fa-cube',
            ],
            DepartmentTabsEnum::FAMILIES => [
                'title' => __('families'),
                'icon'  => 'fal fa-cubes',
            ],
            DepartmentTabsEnum::SALES => [
                'title' => __('sales'),
                'icon'  => 'fal fa-money-bill-wave',
            ],
            DepartmentTabsEnum::CUSTOMERS => [
                'title' => __('customers'),
                'icon'  => 'fal fa-user',
            ],DepartmentTabsEnum::OFFERS => [
                'title' => __('offers'),
                'icon'  => 'fal fa-tags',
            ],DepartmentTabsEnum::MAILSHOTS => [
                'title' => __('mailshots'),
                'icon'  => 'fal fa-bullhorn',
            ],DepartmentTabsEnum::RELATED_CATEGORIES => [
                'title' => __('related categories'),
                'icon'  => 'fal fa-project-diagram',
            ],DepartmentTabsEnum::IMAGES=> [
                'title' => __('images'),
                'icon'  => 'fal fa-camera-retro',
                'type'  => 'icon',
                'align' => 'right',
            ],DepartmentTabsEnum::HISTORY => [
                'title' => __('history'),
                'icon'  => 'fal fa-clock',
                'type'  => 'icon',
                'align' => 'right',
            ],
            DepartmentTabsEnum::SHOWCASE => [
                'title' => __('department'),
                'icon'  => 'fas fa-info-circle',
            ],
        };
    }
}
