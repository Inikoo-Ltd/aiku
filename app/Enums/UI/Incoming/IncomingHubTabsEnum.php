<?php

namespace App\Enums\UI\Incoming;

use App\Enums\EnumHelperTrait;
use App\Enums\HasTabs;

enum IncomingHubTabsEnum: string
{
    use EnumHelperTrait;
    use HasTabs;

    case STOCK_DELIVERIES = 'stock_deliveries';
    case PALLET_DELIVERIES = 'pallet_deliveries';
    case RETURN_DELIVERY_NOTES = 'return_delivery_notes';

    public function blueprint(): array
    {
        return match ($this) {
            IncomingHubTabsEnum::STOCK_DELIVERIES => [
                'title' => __('Stock Deliveries'),
                'icon'  => 'fal fa-truck-container',
            ],
            IncomingHubTabsEnum::PALLET_DELIVERIES => [
                'title' => __('Fulfilment Deliveries'),
                'icon'  => 'fal fa-truck-couch',
            ],
            IncomingHubTabsEnum::RETURN_DELIVERY_NOTES => [
                'title' => __('Returns'),
                'icon'  => 'fal fa-exchange',
            ],
        };
    }
}
