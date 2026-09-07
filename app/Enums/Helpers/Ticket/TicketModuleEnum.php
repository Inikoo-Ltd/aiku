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
    case BILLABLES       = 'billables';
    case CATALOGUE       = 'catalogue';
    case CHAT            = 'chat';
    case COMMS           = 'comms';
    case CRM             = 'crm';
    case DISCOUNTS       = 'discounts';
    case DISPATCHING     = 'dispatching';
    case DROPSHIPPING    = 'dropshipping';
    case FULFILMENT      = 'fulfilment';
    case GOODS           = 'goods';
    case GOODS_IN        = 'goods_in';
    case HUMAN_RESOURCES = 'human_resources';
    case INVENTORY       = 'inventory';
    case IRIS            = 'iris';
    case MAINTENANCE     = 'maintenance';
    case MASTERS         = 'masters';
    case ORDERING        = 'ordering';
    case PROCUREMENT     = 'procurement';
    case PRODUCTION      = 'production';
    case REPORTS         = 'reports';
    case RETINA          = 'retina';
    case REVIEWS         = 'reviews';
    case SEARCH          = 'search';
    case SUPPLY_CHAIN    = 'supply_chain';
    case SYSADMIN        = 'sysadmin';
    case TRANSFERS       = 'transfers';
    case WEB             = 'web';
    case DEVOPS          = 'devops';

    public static function labels(): array
    {
        return [
            'accounting'      => __('Accounting'),
            'billables'       => __('Billables'),
            'catalogue'       => __('Catalogue'),
            'chat'            => __('Chat'),
            'comms'           => __('Comms'),
            'crm'             => __('CRM'),
            'discounts'       => __('Discounts'),
            'dispatching'     => __('Dispatching'),
            'dropshipping'    => __('Dropshipping'),
            'fulfilment'      => __('Fulfilment'),
            'goods'           => __('Goods'),
            'goods_in'        => __('Goods in'),
            'human_resources' => __('Human resources'),
            'inventory'       => __('Inventory'),
            'iris'            => __('Iris (websites)'),
            'maintenance'     => __('Maintenance'),
            'masters'         => __('Masters'),
            'ordering'        => __('Ordering'),
            'procurement'     => __('Procurement'),
            'production'      => __('Production'),
            'reports'         => __('Reports'),
            'retina'          => __('Retina (customer app)'),
            'reviews'         => __('Reviews'),
            'search'          => __('Search'),
            'supply_chain'    => __('Supply chain'),
            'sysadmin'        => __('Sysadmin'),
            'transfers'       => __('Transfers'),
            'web'             => __('Web (workshop)'),
            'devops'          => __('DevOps'),
        ];
    }
}
