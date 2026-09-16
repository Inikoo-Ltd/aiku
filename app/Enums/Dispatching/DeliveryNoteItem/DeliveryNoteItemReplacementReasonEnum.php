<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 15 Sept 2026 16:30:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Enums\Dispatching\DeliveryNoteItem;

use App\Enums\EnumHelperTrait;

enum DeliveryNoteItemReplacementReasonEnum: string
{
    use EnumHelperTrait;

    case DAMAGED_IN_TRANSIT = 'damaged_in_transit';
    case LOST_IN_TRANSIT = 'lost_in_transit';
    case WRONG_ITEM_SENT = 'wrong_item_sent';
    case MISSING_FROM_PARCEL = 'missing_from_parcel';
    case POOR_PACKAGING = 'poor_packaging';
    case FAULTY_PRODUCT = 'faulty_product';
    case CUSTOMER_ERROR = 'customer_error';
    case OTHER = 'other';

    public function label(): string
    {
        return match ($this) {
            self::DAMAGED_IN_TRANSIT => __('Damaged by courier'),
            self::LOST_IN_TRANSIT => __('Lost by courier'),
            self::WRONG_ITEM_SENT => __('Wrong item sent'),
            self::MISSING_FROM_PARCEL => __('Missing from parcel'),
            self::POOR_PACKAGING => __('Broken, poor packaging'),
            self::FAULTY_PRODUCT => __('Faulty product'),
            self::CUSTOMER_ERROR => __('Customer error'),
            self::OTHER => __('Other'),
        };
    }

    public function responsible(): string
    {
        return match ($this) {
            self::DAMAGED_IN_TRANSIT, self::LOST_IN_TRANSIT => 'courier',
            self::WRONG_ITEM_SENT, self::MISSING_FROM_PARCEL, self::POOR_PACKAGING => 'warehouse',
            self::FAULTY_PRODUCT => 'supplier',
            self::CUSTOMER_ERROR => 'customer',
            self::OTHER => 'unknown',
        };
    }
}
