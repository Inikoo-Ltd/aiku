<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 23 Sep 2026, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Enums\SysAdmin\McpChange;

use App\Enums\EnumHelperTrait;

enum McpChangeTypeEnum: string
{
    use EnumHelperTrait;

    case RELATED_PRODUCTS = 'related_products';
    case ORG_STOCK_STATE = 'org_stock_state';
    case PARTNER_SHOPPING_LIST = 'partner_shopping_list';
    case PRODUCTION_RECORD = 'production_record';
    case PRODUCTION_RECIPE = 'production_recipe';
    case PLACED_ORDER = 'placed_order';

    public static function labels(): array
    {
        return [
            'related_products' => __('Related products'),
            'org_stock_state'       => __('SKO state'),
            'partner_shopping_list' => __('Hub shopping list'),
            'production_record'     => __('Artefact, raw material or task'),
            'production_recipe'     => __('Artefact recipe'),
            'placed_order'          => __('Order placed'),
        ];
    }

    /**
     * The user switch that lets someone make, and therefore also undo, this kind of change.
     */
    public function userSwitch(): string
    {
        return match ($this) {
            McpChangeTypeEnum::RELATED_PRODUCTS => 'can_use_mcp_web',
            McpChangeTypeEnum::ORG_STOCK_STATE  => 'can_use_mcp_discontinue',
            McpChangeTypeEnum::PARTNER_SHOPPING_LIST => 'can_use_mcp_procurement',
            McpChangeTypeEnum::PRODUCTION_RECORD, McpChangeTypeEnum::PRODUCTION_RECIPE => 'can_use_mcp_production',
            McpChangeTypeEnum::PLACED_ORDER => 'can_use_mcp_place_orders',
        };
    }
}
