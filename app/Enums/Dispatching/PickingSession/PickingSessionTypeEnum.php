<?php

namespace App\Enums\Dispatching\PickingSession;

use App\Enums\EnumHelperTrait;
use App\Enums\HasTabs;

enum PickingSessionTypeEnum: string
{
    use EnumHelperTrait;
    use HasTabs;

    case DROPSHIPPING = 'dropshipping';
    case FULFILMENT   = 'fulfilment';

    public function label(): string
    {
        return match ($this) {
            self::DROPSHIPPING => __('Dropshipping'),
            self::FULFILMENT   => __('Fulfilment'),
        };
    }

    public function blueprint(): array
    {
        return match ($this) {
            self::DROPSHIPPING => [
                'title' => __('Dropshipping'),
                'icon'  => 'fal fa-shopping-cart',
            ],
            self::FULFILMENT   => [
                'title' => __('Fulfilment'),
                'icon'  => 'fal fa-warehouse-alt',
            ],
        };
    }
}
