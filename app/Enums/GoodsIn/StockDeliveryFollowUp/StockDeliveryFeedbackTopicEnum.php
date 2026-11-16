<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 13 Nov 2026 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Enums\GoodsIn\StockDeliveryFollowUp;

use App\Enums\EnumHelperTrait;

enum StockDeliveryFeedbackTopicEnum: string
{
    use EnumHelperTrait;

    case PRODUCT_QUALITY = 'product_quality';
    case QUANTITIES = 'quantities';
    case PACKING = 'packing';
    case CARTON_LABELS = 'carton_labels';
    case DOCUMENTS = 'documents';
    case OTHER = 'other';

    public static function labels(): array
    {
        return [
            'product_quality' => __('Product quality'),
            'quantities' => __('Quantities'),
            'packing' => __('Packing'),
            'carton_labels' => __('Carton labels'),
            'documents' => __('Documents'),
            'other' => __('Other'),
        ];
    }

    public function label(): string
    {
        return self::labels()[$this->value];
    }
}
