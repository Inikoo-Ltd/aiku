<?php

/*
 * Author: Artha <artha@aw-advantage.com>
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Enums\Dropshipping;

use App\Enums\EnumHelperTrait;

enum PortfolioConnectionAuditEnum: string
{
    use EnumHelperTrait;

    case CONNECTED = 'connected';
    case NOT_LINKED = 'not_linked';
    case INVALID_PRODUCT_ID = 'invalid_product_id';
    case MISSING_IN_PLATFORM = 'missing_in_platform';
    case INACTIVE_IN_PLATFORM = 'inactive_in_platform';
    case WRONG_PRODUCT_LINKED = 'wrong_product_linked';
    case NOT_AT_FULFILMENT_LOCATION = 'not_at_fulfilment_location';
    case NO_SKU = 'no_sku';

    public static function labels(): array
    {
        return [
            'connected'                  => __('Connected'),
            'not_linked'                 => __('Never linked to the shop'),
            'invalid_product_id'         => __('Stored id is not a Shopify product id'),
            'missing_in_platform'        => __('Linked product no longer in the shop'),
            'inactive_in_platform'       => __('Linked product is not active'),
            'wrong_product_linked'       => __('Linked to a product with a different SKU'),
            'not_at_fulfilment_location' => __('Linked but not stocked at our fulfilment location'),
            'no_sku'                     => __('Portfolio has no SKU to reconcile against'),
        ];
    }

    public function isConnected(): bool
    {
        return $this === self::CONNECTED;
    }
}
