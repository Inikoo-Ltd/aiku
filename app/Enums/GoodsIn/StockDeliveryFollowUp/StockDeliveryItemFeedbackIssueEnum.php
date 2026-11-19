<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 19 Nov 2026 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Enums\GoodsIn\StockDeliveryFollowUp;

use App\Enums\EnumHelperTrait;

enum StockDeliveryItemFeedbackIssueEnum: string
{
    use EnumHelperTrait;

    case DAMAGED = 'damaged';
    case PRODUCT_QUALITY = 'product_quality';
    case WRONG_ITEM = 'wrong_item';
    case PACKING = 'packing';
    case CARTON_LABELS = 'carton_labels';
    case OTHER = 'other';

    public static function labels(): array
    {
        return [
            'damaged'         => __('Damaged or broken'),
            'product_quality' => __('Poor quality, rotten or faulty'),
            'wrong_item'      => __('Wrong item'),
            'packing'         => __('Packing'),
            'carton_labels'   => __('Carton labels'),
            'other'           => __('Other'),
        ];
    }

    public function label(): string
    {
        return self::labels()[$this->value];
    }

    public function topic(): StockDeliveryFeedbackTopicEnum
    {
        return match ($this) {
            self::DAMAGED, self::PRODUCT_QUALITY, self::WRONG_ITEM => StockDeliveryFeedbackTopicEnum::PRODUCT_QUALITY,
            self::PACKING                                          => StockDeliveryFeedbackTopicEnum::PACKING,
            self::CARTON_LABELS                                    => StockDeliveryFeedbackTopicEnum::CARTON_LABELS,
            self::OTHER                                            => StockDeliveryFeedbackTopicEnum::OTHER,
        };
    }
}
