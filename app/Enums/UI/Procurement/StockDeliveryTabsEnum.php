<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 27 May 2024 13:07:55 British Summer Time, Sheffield, UK
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Enums\UI\Procurement;

use App\Enums\EnumHelperTrait;
use App\Enums\HasTabs;

enum StockDeliveryTabsEnum: string
{
    use EnumHelperTrait;
    use HasTabs;

    case ITEMS                = 'items';
    case PENDING_ITEMS        = 'pending_items';
    case DONE_ITEMS           = 'done_items';
    case CHECKLIST            = 'checklist';
    case PACKING_CHECKS       = 'packing_checks';
    case UNDER_OVER_DELIVERED = 'under_over_delivered';
    case TARIFF_CODES         = 'tariff_codes';
    case CUSTOMS              = 'customs';
    case PURCHASE_ORDERS      = 'purchase_orders';
    case ATTACHMENTS          = 'attachments';
    case NOTES = 'notes';
    case HISTORY              = 'history';

    public function blueprint(): array
    {
        return match ($this) {
            StockDeliveryTabsEnum::ITEMS                => [
                'title' => __('Items'),
                'icon'  => 'fal fa-bars',
            ],
            StockDeliveryTabsEnum::PENDING_ITEMS        => [
                'title' => __('Pending Items'),
                'icon'  => 'fal fa-clipboard-list-check',
            ],
            StockDeliveryTabsEnum::DONE_ITEMS           => [
                'title' => __('Done Items'),
                'icon'  => 'fal fa-clipboard-check',
            ],
            StockDeliveryTabsEnum::CHECKLIST            => [
                'title' => __('Checklist'),
                'icon'  => 'fal fa-tasks',
            ],
            StockDeliveryTabsEnum::PACKING_CHECKS       => [
                'title' => __('Packing checks'),
                'icon'  => 'fal fa-weight',
            ],
            StockDeliveryTabsEnum::UNDER_OVER_DELIVERED => [
                'title' => __('Under/Over delivered items'),
                'icon'  => 'fal fa-box-open',
            ],
            StockDeliveryTabsEnum::TARIFF_CODES         => [
                'title' => __('Tariff codes'),
                'icon'  => 'fal fa-globe',
            ],
            StockDeliveryTabsEnum::CUSTOMS              => [
                'title' => __('Customs'),
                'icon'  => 'fal fa-passport',
            ],
            StockDeliveryTabsEnum::PURCHASE_ORDERS      => [
                'title' => __('Purchase Orders'),
                'icon'  => 'fal fa-clipboard-list',
            ],
            StockDeliveryTabsEnum::ATTACHMENTS          => [
                'title' => __('Attachments'),
                'icon'  => 'fal fa-paperclip',
                'type'  => 'icon',
                'align' => 'right',
            ],
            StockDeliveryTabsEnum::NOTES => [
                'title' => __('Notes'),
                'icon'  => 'fal fa-sticky-note',
            ],
            StockDeliveryTabsEnum::HISTORY              => [
                'title' => __('History'),
                'icon'  => 'fal fa-clock',
                'type'  => 'icon',
                'align' => 'right',
            ],
        };
    }
}
