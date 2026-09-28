<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 11 May 2024 23:14:41 British Summer Time, Sheffield, UK
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Enums\UI\Procurement;

use App\Enums\EnumHelperTrait;
use App\Enums\HasTabs;

enum PurchaseOrderTabsEnum: string
{
    use EnumHelperTrait;
    use HasTabs;

    case ITEMS        = 'items';
    case PRODUCTS     = 'products';
    case SHOWCASE     = 'showcase';
    case NOTES = 'notes';
    case ATTACHMENTS = 'attachments';
    case DISPATCHED_EMAILS = 'dispatched_emails';
    case HISTORY      = 'history';

    public function blueprint(): array
    {
        return match ($this) {
            PurchaseOrderTabsEnum::ITEMS    => [
                'title' => __('Items'),
                'icon'  => 'fal fa-bars',
            ],
            PurchaseOrderTabsEnum::PRODUCTS => [
                'title' => __("All supplier's products"),
                'icon'  => 'fal fa-th-list',
            ],
            PurchaseOrderTabsEnum::SHOWCASE => [
                'title' => __('Showcase'),
                'icon'  => 'fal fa-database',
            ],
            PurchaseOrderTabsEnum::NOTES => [
                'title' => __('Notes'),
                'icon'  => 'fal fa-sticky-note',
            ],
            PurchaseOrderTabsEnum::ATTACHMENTS => [
                'title' => __('Attachments'),
                'icon'  => 'fal fa-paperclip',
                'type'  => 'icon',
                'align' => 'right',
            ],
            PurchaseOrderTabsEnum::DISPATCHED_EMAILS => [
                'title' => __('Emails sent'),
                'icon'  => 'fal fa-envelope',
                'type'  => 'icon',
                'align' => 'right',
            ],
            PurchaseOrderTabsEnum::HISTORY  => [
                'title' => __('History'),
                'type'  => 'icon',
                'icon'  => 'fal fa-clock',
                'align' => 'right',
            ],
        };
    }
}
