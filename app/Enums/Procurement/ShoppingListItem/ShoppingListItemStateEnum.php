<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 10 Aug 2026 Malaga, Spain
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Enums\Procurement\ShoppingListItem;

use App\Enums\EnumHelperTrait;

enum ShoppingListItemStateEnum: string
{
    use EnumHelperTrait;

    case DRAFT = 'draft';
    case OPEN = 'open';
    case DISMISS_PROPOSED = 'dismiss_proposed';
    case ORDERED = 'ordered';
    case DISMISSED = 'dismissed';

    /**
     * A partner shopping list as the buyer sees it: drafts it is still preparing and the lines it
     * submitted. The seller only ever works with open lines.
     *
     * @return array<int, string>
     */
    public static function onPartnerBuyerList(): array
    {
        return [self::DRAFT->value, self::OPEN->value];
    }

    public static function labels(): array
    {
        return [
            'draft'            => __('Draft'),
            'open'             => __('Open'),
            'dismiss_proposed' => __('Dismissal proposed'),
            'ordered'          => __('Ordered'),
            'dismissed'        => __('Dismissed'),
        ];
    }
}
