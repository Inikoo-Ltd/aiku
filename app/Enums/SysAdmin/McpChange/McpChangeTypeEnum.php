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
    case SUPPLIER_PRODUCT_AVAILABILITY = 'supplier_product_availability';
    case PARTNER_SHOPPING_LIST = 'partner_shopping_list';
    case PRODUCTION_RECORD = 'production_record';
    case PRODUCTION_RECIPE = 'production_recipe';
    case PLACED_ORDER = 'placed_order';
    case MASTER_PRICES = 'master_prices';
    case STOCK_DELIVERY_COSTS = 'stock_delivery_costs';
    case CUSTOMER_SERVICE = 'customer_service';
    case CHAT_SPAM = 'chat_spam';
    case CHAT_CLOSED = 'chat_closed';
    case TRADE_UNIT_PROPERTIES = 'trade_unit_properties';
    case WEBSITE_REDIRECTS = 'website_redirects';
    case STOCK_LOCATIONS = 'stock_locations';
    case PRODUCTION_SETUP = 'production_setup';
    case SUPPLIER_PRODUCT_LINKS = 'supplier_product_links';
    case ORG_STOCK_AUTO_ORDERING = 'org_stock_auto_ordering';
    case INFO_WEBSITE_CONTENT = 'info_website_content';

    public static function labels(): array
    {
        return [
            'related_products' => __('Related products'),
            'org_stock_state'       => __('SKO state'),
            'supplier_product_availability' => __('Supplier product availability'),
            'partner_shopping_list' => __('Hub shopping list'),
            'production_record'     => __('Artefact, raw material or task'),
            'production_recipe'     => __('Artefact recipe'),
            'placed_order'          => __('Order placed'),
            'master_prices'         => __('Master prices'),
            'stock_delivery_costs'  => __('Stock delivery costs'),
            'customer_service'      => __('Customer service'),
            'chat_spam'             => __('Chat put in spam'),
            'chat_closed'           => __('Chat closed after thanks'),
            'trade_unit_properties' => __('Trade unit properties'),
            'website_redirects'     => __('Website redirects'),
            'stock_locations'       => __('SKO locations'),
            'production_setup'      => __('Factory location and work areas'),
            'supplier_product_links' => __('SKOs attached to suppliers'),
            'org_stock_auto_ordering' => __('SKOs kept out of order suggestions'),
            'info_website_content'  => __('Info website pages and shared parts'),
        ];
    }

    /**
     * The user switch that lets someone make, and therefore also undo, this kind of change.
     */
    public function userSwitch(): string
    {
        return match ($this) {
            McpChangeTypeEnum::RELATED_PRODUCTS, McpChangeTypeEnum::WEBSITE_REDIRECTS => 'can_use_mcp_web',
            McpChangeTypeEnum::ORG_STOCK_STATE, McpChangeTypeEnum::SUPPLIER_PRODUCT_AVAILABILITY => 'can_use_mcp_discontinue',
            McpChangeTypeEnum::PARTNER_SHOPPING_LIST, McpChangeTypeEnum::SUPPLIER_PRODUCT_LINKS, McpChangeTypeEnum::ORG_STOCK_AUTO_ORDERING => 'can_use_mcp_procurement',
            McpChangeTypeEnum::PRODUCTION_RECORD, McpChangeTypeEnum::PRODUCTION_RECIPE, McpChangeTypeEnum::PRODUCTION_SETUP => 'can_use_mcp_production',
            McpChangeTypeEnum::PLACED_ORDER => 'can_use_mcp_place_orders',
            McpChangeTypeEnum::MASTER_PRICES => 'can_use_mcp_prices',
            McpChangeTypeEnum::STOCK_DELIVERY_COSTS => 'can_use_mcp_costing',
            McpChangeTypeEnum::CUSTOMER_SERVICE, McpChangeTypeEnum::CHAT_SPAM, McpChangeTypeEnum::CHAT_CLOSED => 'can_use_mcp_customer_service',
            McpChangeTypeEnum::TRADE_UNIT_PROPERTIES => 'can_use_mcp_goods',
            McpChangeTypeEnum::STOCK_LOCATIONS => 'can_use_mcp_locations',
            McpChangeTypeEnum::INFO_WEBSITE_CONTENT => 'can_use_mcp_info_websites',
        };
    }

    /**
     * An order sent to a supplier, a reply emailed to a customer or a credit on their balance has
     * already left the building: it is undone by a person, with the other party, never from the log.
     */
    public function canBeReverted(): bool
    {
        return !in_array($this, [McpChangeTypeEnum::PLACED_ORDER, McpChangeTypeEnum::CUSTOMER_SERVICE]);
    }
}
