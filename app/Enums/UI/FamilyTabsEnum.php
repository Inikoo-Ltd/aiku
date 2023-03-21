<?php
/*
 * Author: Jonathan Lopez Sanchez <jonathan@ancientwisdom.biz>
 * Created: Mon, 20 Mar 2023 14:46:07 Central European Standard Time, Malaga, Spain
 * Copyright (c) 2023, Inikoo LTD
 */

namespace App\Enums\UI;

use App\Enums\EnumHelperTrait;
use App\Enums\HasTabs;

enum FamilyTabsEnum: string
{
    use EnumHelperTrait;
    use HasTabs;




    case DATA                = 'data';
    case SUBCATEGORIES       = 'subcategories';
    case SALES               = 'sales';
    case CUSTOMERS           = 'customers';
    case OFFERS              = 'offers';
    case MAILSHOTS           = 'mailshots';
    case RELATED_CATEGORIES  = 'related_categories';
    case IMAGES              = 'images';

    case CHANGELOG           = 'changelog';


    public function blueprint(): array
    {
        return match ($this) {
            FamilyTabsEnum::DATA => [
                'title' => __('items'),
                'icon'  => 'fal fa-database',
            ],
            FamilyTabsEnum::SUBCATEGORIES => [
                'title' => __('payments'),
            ],
            FamilyTabsEnum::SALES => [
                'title' => __('sales'),
                'icon'  => 'fal fa-money-bill-wave',
            ],
            FamilyTabsEnum::CUSTOMERS => [
                'title' => __('customers'),
                'icon'  => 'fal fa-user',
            ],FamilyTabsEnum::OFFERS => [
                'title' => __('offers'),
                'icon'  => 'fal fa-tags',
            ],FamilyTabsEnum::MAILSHOTS => [
                'title' => __('mailshots'),
                'icon'  => 'fal fa-bullhorn',
            ],FamilyTabsEnum::RELATED_CATEGORIES => [
                'title' => __('related categories'),
                'icon'  => 'fal fa-project-diagram',
            ],FamilyTabsEnum::IMAGES=> [
                'title' => __('images'),
                'icon'  => 'fal fa-camera-retro',
                'type'  => 'icon-only',
            ],FamilyTabsEnum::CHANGELOG => [
                'title' => __('changelog'),
                'icon'  => 'fal fa-road',
                'type'  => 'icon-only',
            ],
        };
    }
}
