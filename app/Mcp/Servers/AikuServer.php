<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 22 Jul 2026 00:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Mcp\Servers;

use App\Mcp\Tools\CustomerConversionTool;
use App\Mcp\Tools\CustomerEmailPressureTool;
use App\Mcp\Tools\CustomerLookupTool;
use App\Mcp\Tools\CustomerNotesTool;
use App\Mcp\Tools\DescribeTablesTool;
use App\Mcp\Resources\AikuDataGuideResource;
use App\Mcp\Tools\DeliveryNotesSummaryTool;
use App\Mcp\Tools\EmployeeAttendanceTool;
use App\Mcp\Tools\EmployeeDirectoryTool;
use App\Mcp\Tools\FamilySalesTool;
use App\Mcp\Tools\GroupSalesTool;
use App\Mcp\Tools\MailshotPerformanceTool;
use App\Mcp\Tools\MarketingPerformanceTool;
use App\Mcp\Tools\MarketingTrendTool;
use App\Mcp\Tools\EmailMarketingPerformanceTool;
use App\Mcp\Tools\OfferPerformanceTool;
use App\Mcp\Tools\MarginTrendTool;
use App\Mcp\Tools\MyAccessTool;
use App\Mcp\Tools\OffersOverviewTool;
use App\Mcp\Tools\OrderStatusTool;
use App\Mcp\Tools\OrderFunnelTool;
use App\Mcp\Tools\OrgFamilySalesTool;
use App\Mcp\Tools\OrgStockSalesTool;
use App\Mcp\Tools\ProductLookupTool;
use App\Mcp\Tools\ProductsWithoutImagesTool;
use App\Mcp\Tools\RefundsByProductTool;
use App\Mcp\Tools\ShopReviewsTool;
use App\Mcp\Tools\ShopSalesTool;
use App\Mcp\Tools\SlowStockTool;
use App\Mcp\Tools\SqlQueryTool;
use App\Mcp\Tools\StaffChatAnalyticsTool;
use App\Mcp\Tools\StockLevelsTool;
use App\Mcp\Tools\TopProductsTool;
use App\Mcp\Tools\TradeUnitFamilySalesTool;
use App\Mcp\Tools\TradeUnitSalesTool;
use App\Mcp\Tools\WarehousePerformanceTool;
use App\Mcp\Tools\WebsiteOverviewTool;
use App\Mcp\Tools\WebTrafficTool;
use Laravel\Mcp\Server;
use Laravel\Mcp\Server\Attributes\Instructions;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Version;

#[Name('Aiku')]
#[Version('1.0.0')]
#[Instructions('Read-only access to Aiku commerce data. Every tool is scoped by the authenticated user\'s permissions: a tool call against a shop the user cannot view returns a permission error. Tools identify shops, organisations and warehouses by slug, never by their display name — when a question names one in words, call my-access-tool first to get the slugs this user can reach, and never guess a slug. For questions about a specific product or customer use product-lookup-tool and customer-lookup-tool. For marketing questions — traffic sources, where customers come from, ad spend and return (ROAS/ROI), Google Ads or Meta Ads effectiveness, SEO/organic trend, AI assistant traffic, which newsletter earned most — use marketing-performance-tool, marketing-trend-tool and email-marketing-performance-tool; they encode the attribution rules, do not reconstruct them in SQL. sql-query-tool and describe-tables-tool only work for users with SQL access enabled: if they return an access error, do not retry them and answer with the other tools instead.')]
class AikuServer extends Server
{
    /**
     * @var array<int, class-string<\Laravel\Mcp\Server\Tool>>
     */
    protected array $tools = [
        MyAccessTool::class,
        ProductLookupTool::class,
        CustomerLookupTool::class,
        ShopSalesTool::class,
        TopProductsTool::class,
        OrderStatusTool::class,
        StockLevelsTool::class,
        DeliveryNotesSummaryTool::class,
        WarehousePerformanceTool::class,
        EmployeeDirectoryTool::class,
        EmployeeAttendanceTool::class,
        WebsiteOverviewTool::class,
        WebTrafficTool::class,
        ProductsWithoutImagesTool::class,
        FamilySalesTool::class,
        OffersOverviewTool::class,
        MailshotPerformanceTool::class,
        MarketingPerformanceTool::class,
        MarketingTrendTool::class,
        EmailMarketingPerformanceTool::class,
        OfferPerformanceTool::class,
        CustomerEmailPressureTool::class,
        ShopReviewsTool::class,
        CustomerNotesTool::class,
        OrgFamilySalesTool::class,
        OrgStockSalesTool::class,
        GroupSalesTool::class,
        TradeUnitFamilySalesTool::class,
        TradeUnitSalesTool::class,
        SlowStockTool::class,
        OrderFunnelTool::class,
        CustomerConversionTool::class,
        RefundsByProductTool::class,
        MarginTrendTool::class,
        StaffChatAnalyticsTool::class,
        SqlQueryTool::class,
        DescribeTablesTool::class,
    ];

    /**
     * @var array<int, class-string<\Laravel\Mcp\Server\Resource>>
     */
    protected array $resources = [
        AikuDataGuideResource::class,
    ];
}
