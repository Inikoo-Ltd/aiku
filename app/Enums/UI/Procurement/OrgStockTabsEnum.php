<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 13 Aug 2024 17:14:04 Central Indonesia Time, Bali, Indonesia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Enums\UI\Procurement;

use App\Enums\EnumHelperTrait;
use App\Enums\HasTabs;

enum OrgStockTabsEnum: string
{
    use EnumHelperTrait;
    use HasTabs;

    case SHOWCASE = 'showcase';
    case SALES_ANALYSIS = 'sales_analysis';
    case TRADE_UNITS = 'trade_units';
    case HISTORY = 'history';
    case IMAGES = 'images';
    case ATTACHMENTS = 'attachments';
    case FEEDBACKS = 'feedbacks';
    case PURCHASE_ORDERS = 'purchase_orders';
    case SUPPLIER_PRODUCTS = 'supplier_products';
    case ARTEFACT_CURRENT_ORDERS = 'artefact_current_orders';
    case ARTEFACT_BATCHES = 'artefact_batches';
    case ARTEFACT_MANUFACTURE_TASKS = 'artefact_manufacture_tasks';
    case ARTEFACT_LABELS = 'artefact_labels';
    case ARTEFACT_COMPLIANCE = 'artefact_compliance';

    /**
     * Production tabs, only for the stock a manufacturing hub makes itself.
     *
     * @return array<int, OrgStockTabsEnum>
     */
    public static function artefactTabs(): array
    {
        return [self::ARTEFACT_CURRENT_ORDERS, self::ARTEFACT_BATCHES, self::ARTEFACT_MANUFACTURE_TASKS, self::ARTEFACT_LABELS, self::ARTEFACT_COMPLIANCE];
    }

    public function blueprint(): array
    {
        return match ($this) {
            OrgStockTabsEnum::SALES_ANALYSIS => [
                'title' => __('Sales'),
                'icon'  => 'fal fa-chart-line',
            ],

            OrgStockTabsEnum::HISTORY => [
                'align' => 'right',
                'title' => __('History'),
                'icon'  => 'fal fa-clock',
                'type'  => 'icon',
            ],

            OrgStockTabsEnum::FEEDBACKS => [
                'title' => __('Issues'),
                'icon'  => 'fal fa-poop',
            ],

            OrgStockTabsEnum::TRADE_UNITS => [
                'title' => __('Trade units'),
                'icon'  => 'fal fa-atom',
            ],

            OrgStockTabsEnum::ATTACHMENTS => [
                'align' => 'right',
                'type'  => 'icon',
                'title' => __('Attachments'),
                'icon'  => 'fal fa-paperclip',
            ],

            OrgStockTabsEnum::IMAGES => [
                'align' => 'right',
                'type'  => 'icon',
                'title' => __('Images'),
                'icon'  => 'fal fa-camera-retro',
            ],

            OrgStockTabsEnum::SHOWCASE => [
                'title' => __('Overview'),
                'icon'  => 'fal fa-tachometer-alt-fast',
            ],

            OrgStockTabsEnum::PURCHASE_ORDERS => [
                'title' => __('Purchase orders'),
                'icon'  => 'fal fa-clipboard',
            ],

            OrgStockTabsEnum::SUPPLIER_PRODUCTS => [
                'title' => __("Supplier's products"),
                'icon'  => 'fal fa-hand-receiving',
            ],

            OrgStockTabsEnum::ARTEFACT_CURRENT_ORDERS => [
                'align' => 'right',
                'type'  => 'icon',
                'title' => __('Current orders'),
                'icon'  => 'fal fa-clipboard-list',
            ],

            OrgStockTabsEnum::ARTEFACT_BATCHES => [
                'align' => 'right',
                'type'  => 'icon',
                'title' => __('Production history'),
                'icon'  => 'fal fa-layer-group',
            ],

            OrgStockTabsEnum::ARTEFACT_MANUFACTURE_TASKS => [
                'align' => 'right',
                'type'  => 'icon',
                'title' => __('Manufacture tasks'),
                'icon'  => 'fal fa-hamsa',
            ],

            OrgStockTabsEnum::ARTEFACT_LABELS => [
                'align' => 'right',
                'type'  => 'icon',
                'title' => __('Labels'),
                'icon'  => 'fal fa-tags',
            ],

            OrgStockTabsEnum::ARTEFACT_COMPLIANCE => [
                'align' => 'right',
                'type'  => 'icon',
                'title' => __('Compliance'),
                'icon'  => 'fal fa-clipboard-check',
            ],
        };
    }
}
