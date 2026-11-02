<?php

/*
 * Author Louis Perez
 * Created on 15-10-2026-17h-55m
 * GitHub: https://github.com/louis-perez
 * Copyright 2026
*/

namespace App\Enums\UI\Goods;

use App\Enums\EnumHelperTrait;
use App\Enums\HasTabs;

enum ProductDevelopmentTabsEnum: string
{
    use EnumHelperTrait;
    use HasTabs;

    case BRIEF      = 'brief';
    case PRODUCTS   = 'products';
    case FORMULATION = 'formulation';
    case BUYERS_LIST = 'buyers_list';
    case COMPLIANCE = 'compliance';
    case DESIGN     = 'design';
    case FILES      = 'files';
    case TASKS      = 'tasks';
    case ACTIVITY   = 'activity';

    public function blueprint(): array
    {
        return match ($this) {
            ProductDevelopmentTabsEnum::BRIEF => [
                'title' => __('Brief'),
                'icon'  => 'fal fa-clipboard',
            ],
            ProductDevelopmentTabsEnum::PRODUCTS => [
                'title' => __('Products'),
                'icon'  => 'fal fa-atom',
            ],
            ProductDevelopmentTabsEnum::FORMULATION => [
                'title' => __('Formulation & trial runs'),
                'icon'  => 'fal fa-flask',
            ],
            ProductDevelopmentTabsEnum::BUYERS_LIST => [
                'title' => __("Buyer's list"),
                'icon'  => 'fal fa-shopping-cart',
            ],
            ProductDevelopmentTabsEnum::COMPLIANCE => [
                'title' => __('Compliance'),
                'icon'  => 'fal fa-shield-check',
            ],
            ProductDevelopmentTabsEnum::DESIGN => [
                'title' => __('Design'),
                'icon'  => 'fal fa-pencil-ruler',
            ],
            ProductDevelopmentTabsEnum::FILES => [
                'title' => __('Files'),
                'icon'  => 'fal fa-paperclip',
            ],
            ProductDevelopmentTabsEnum::TASKS => [
                'title' => __('Tasks'),
                'icon'  => 'fal fa-tasks',
            ],
            ProductDevelopmentTabsEnum::ACTIVITY => [
                'title' => __('Activity'),
                'icon'  => 'fal fa-clock',
            ],
        };
    }
}
