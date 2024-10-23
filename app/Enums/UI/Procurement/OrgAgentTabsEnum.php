<?php
/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 02 May 2024 19:19:04 British Summer Time, Sheffield, UK
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Enums\UI\Procurement;

use App\Enums\EnumHelperTrait;
use App\Enums\HasTabs;

enum OrgAgentTabsEnum: string
{
    use EnumHelperTrait;
    use HasTabs;

    case SHOWCASE              = 'showcase';
    case ORG_SUPPLIER_PRODUCTS = 'org_supplier_products';
    case DELIVERIES            = 'deliveries';
    case SYSTEM_USERS          = 'system_users';
    case HISTORY               = 'history';
    case DATA                  = 'data';
    case IMAGES                = 'images';


    public function blueprint(): array
    {
        return match ($this) {
            OrgAgentTabsEnum::DATA => [
                'title' => __('data'),
                'icon'  => 'fal fa-database',
                'type'  => 'icon',
                'align' => 'right',
            ],
            OrgAgentTabsEnum::ORG_SUPPLIER_PRODUCTS => [
                'title' => __('products'),
                'icon'  => 'fal fa-box-usd',
            ],
            OrgAgentTabsEnum::DELIVERIES => [
                'title' => __('deliveries'),
                'icon'  => 'fal fa-truck',
            ],
            OrgAgentTabsEnum::IMAGES => [
                'title' => __('images'),
                'icon'  => 'fal fa-camera-retro',
                'type'  => 'icon',
                'align' => 'right',
            ],
            OrgAgentTabsEnum::SYSTEM_USERS => [
                'title' => __('system user'),
                'icon'  => 'fal fa-terminal',
            ],
            OrgAgentTabsEnum::HISTORY => [
                'title' => __('history'),
                'icon'  => 'fal fa-clock',
                'type'  => 'icon',
                'align' => 'right',
            ],
            OrgAgentTabsEnum::SHOWCASE => [
                'title' => __('agent'),
                'icon'  => 'fas fa-info-circle',
            ],
        };
    }
}
