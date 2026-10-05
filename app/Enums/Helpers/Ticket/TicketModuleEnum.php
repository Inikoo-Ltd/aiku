<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 05 Sep 2026 17:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Enums\Helpers\Ticket;

use App\Enums\EnumHelperTrait;

enum TicketModuleEnum: string
{
    use EnumHelperTrait;

    case ACCOUNTING      = 'accounting';
    case PRODUCTS        = 'products';
    case MARKETING       = 'marketing';
    case WEBSITES        = 'websites';
    case PROCUREMENT     = 'procurement';
    case SYSTEM          = 'system';
    case CRM             = 'crm';
    case CHAT            = 'chat';
    case ORDERING        = 'ordering';
    case DISPATCHING     = 'dispatching';
    case INVENTORY       = 'inventory';
    case DROPSHIPPING    = 'dropshipping';
    case FULFILMENT      = 'fulfilment';
    case PRODUCTION      = 'production';
    case HUMAN_RESOURCES = 'human_resources';
    case REPORTS         = 'reports';

    public static function labels(): array
    {
        return [
            'accounting'      => __('Accounting'),
            'products'        => __('Products'),
            'marketing'       => __('Marketing'),
            'websites'        => __('Websites'),
            'procurement'     => __('Procurement'),
            'system'          => __('System'),
            'crm'             => __('CRM'),
            'chat'            => __('Chat'),
            'ordering'        => __('Ordering'),
            'dispatching'     => __('Dispatching'),
            'inventory'       => __('Inventory'),
            'dropshipping'    => __('Dropshipping & integrations'),
            'fulfilment'      => __('Fulfilment'),
            'production'      => __('Production'),
            'human_resources' => __('HR'),
            'reports'         => __('Reports'),
        ];
    }
}
