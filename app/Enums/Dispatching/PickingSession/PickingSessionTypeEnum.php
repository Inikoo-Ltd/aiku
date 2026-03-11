<?php

namespace App\Enums\Dispatching\PickingSession;

enum PickingSessionTypeEnum: string
{
    case DROPSHIPPING = 'dropshipping';
    case FULFILMENT   = 'fulfilment';

    public function label(): string
    {
        return match ($this) {
            self::DROPSHIPPING => __('Dropshipping'),
            self::FULFILMENT   => __('Fulfilment'),
        };
    }
}
