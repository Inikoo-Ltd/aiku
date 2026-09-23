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

    public static function labels(): array
    {
        return [
            'related_products' => __('Related products'),
            'org_stock_state'  => __('SKO state'),
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
        };
    }
}
