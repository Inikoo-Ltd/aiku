<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 22 Jul 2026 00:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Mcp\Servers;

use App\Mcp\Tools\AiChangesTool;
use App\Mcp\Tools\CustomerConversionTool;
use App\Mcp\Tools\CustomerEmailPressureTool;
use App\Mcp\Tools\CustomerLookupTool;
use App\Mcp\Tools\CustomerNotesTool;
use App\Mcp\Tools\DescribeTablesTool;
use App\Mcp\Tools\DiscordMessageTool;
use App\Mcp\Resources\AikuDataGuideResource;
use App\Mcp\Tools\DeliveryNotesSummaryTool;
use App\Mcp\Tools\EmployeeAttendanceTool;
use App\Mcp\Tools\EmployeeDirectoryTool;
use App\Mcp\Tools\FamilyRelatedProductsTool;
use App\Mcp\Tools\HubOrderPlanningTool;
use App\Mcp\Tools\HubShoppingListTool;
use App\Mcp\Tools\FamilySalesTool;
use App\Mcp\Tools\GroupSalesTool;
use App\Mcp\Tools\MailshotPerformanceTool;
use App\Mcp\Tools\MarketingPerformanceTool;
use App\Mcp\Tools\MarketingTrendTool;
use App\Mcp\Tools\EmailMarketingPerformanceTool;
use App\Mcp\Tools\OfferPerformanceTool;
use App\Mcp\Tools\MarginTrendTool;
use App\Mcp\Tools\MyAccessTool;
use App\Mcp\Tools\TicketAttachmentTool;
use App\Mcp\Tools\TicketsTool;
use App\Mcp\Tools\TicketWriteTool;
use App\Mcp\Tools\OffersOverviewTool;
use App\Mcp\Tools\OrderStatusTool;
use App\Mcp\Tools\OrderFunnelTool;
use App\Mcp\Tools\OrgFamilySalesTool;
use App\Mcp\Tools\OrgStockDiscontinuePreviewTool;
use App\Mcp\Tools\OrgStockDiscontinueTool;
use App\Mcp\Tools\OrgStockSalesTool;
use App\Mcp\Tools\PartnerRescueOrderTool;
use App\Mcp\Tools\SupplierPurchaseOrderTool;
use App\Mcp\Tools\ProductionRecipeTool;
use App\Mcp\Tools\ProductionRecordsTool;
use App\Mcp\Tools\ProductLookupTool;
use App\Mcp\Tools\ProductsWithoutImagesTool;
use App\Mcp\Tools\PaymentMethodsTool;
use App\Mcp\Tools\RefundsByProductTool;
use App\Mcp\Tools\ShopReviewsTool;
use App\Mcp\Tools\ShopSalesTool;
use App\Mcp\Tools\SlowStockTool;
use App\Mcp\Tools\SqlQueryTool;
use App\Mcp\Tools\StaffTasksTool;
use App\Mcp\Tools\ProjectsTool;
use App\Mcp\Tools\ProjectWriteTool;
use App\Mcp\Tools\StaffTaskWriteTool;
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
#[Instructions('Access to Aiku commerce data and the Aiku ticketing system. Tickets: tickets-tool lists or shows tickets (HELP-n internal bugs, features and escalations; CUS-n customer tickets) ticket-attachment-tool reads a ticket attachment (text of PDF, Word, CSV; images as images) by reference and file name, and ticket-write-tool comments, assigns, changes status, tags and modules, or raises a new HELP ticket; when the user reports something broken, raise a ticket with ticket-write-tool rather than only answering. discord-message-tool sends a one-way Discord DM to a colleague by aiku username, signed by the authenticated user: use it when asked to tell or ping someone about work done. Staff tasks are how anyone asks a colleague or a department to do something (e.g. ask the warehouse to count a location): staff-task-write-tool creates a TASK-n assigned to a username or a department, optionally linked to a location or SKO, and staff-tasks-tool lists the user\'s tasks or shows one with its thread to follow up. Tickets are for bugs and requests to engineers; tasks are for work people do. Big pieces of work are run as projects that group tickets and tasks under milestones with progress updates: projects-tool lists projects or shows one (milestones, work, updates, commits, workload) and project-write-tool creates or changes them, adds or moves tickets and tasks between milestones, manages milestones and posts updates that notify the team; confirm before deleting milestones or taking work out. org-stock-discontinue-preview-tool and org-stock-discontinue-tool preview and then change the state of SKOs (organisation stock) for the few users enrolled for it; always preview first, show the user what hangs off the SKO, and only call the confirm tool after they have said yes in their own words, passing their request text. family-related-products-tool shows or replaces the related products (\'Sells well with\') of a family for users enrolled to change website content; show the list and the shops affected, and write only after the user confirmed. To plan an order to the manufacturing hub, read hub-order-planning-tool first: per SKO it gives stock, sales, days until out of stock, forecast quantity, order step (whole production batches), shelf life with the most that sells before it expires, and the budget; respect those limits. hub-shopping-list-tool shows or fills that shopping list for users enrolled for it; show the codes and quantities and write only after the user confirmed. Users enrolled to place orders can also submit that basket to the hub, change sent lines the hub has not started (dangerous: warn the user first), and with partner-rescue-order-tool preview and place rescue purchase orders to the other partners, and with supplier-purchase-order-tool preview and place purchase orders to external suppliers, including those bought through an agent (quantities in units, rounded to whole cartons); orders cannot be undone, so place them only after the user confirmed in their own words. To set up what a production makes, for users enrolled for it: production-records-tool shows, creates or edits artefacts (with their SKO), raw materials (with unit cost) and manufacture tasks, and production-recipe-tool shows or replaces the steps of artefacts (task, order, units per artefact, target per hour) and the raw materials each step uses, with the materials cost; show the user what will change and write only after they confirmed. Every change an assistant makes is logged and can be undone: ai-changes-tool lists them and reverts one after the user confirmed. Everything else is read-only. Every tool is scoped by the authenticated user\'s permissions: a tool call against a shop the user cannot view returns a permission error. Tools identify shops, organisations and warehouses by slug, never by their display name — when a question names one in words, call my-access-tool first to get the slugs this user can reach, and never guess a slug. For questions about a specific product or customer use product-lookup-tool and customer-lookup-tool. For marketing questions — traffic sources, where customers come from, ad spend and return (ROAS/ROI), Google Ads or Meta Ads effectiveness, SEO/organic trend, AI assistant traffic, which newsletter earned most — use marketing-performance-tool, marketing-trend-tool and email-marketing-performance-tool; they encode the attribution rules, do not reconstruct them in SQL. sql-query-tool and describe-tables-tool only work for users with SQL access enabled: if they return an access error, do not retry them and answer with the other tools instead.')]
class AikuServer extends Server
{
    /**
     * @var array<int, class-string<\Laravel\Mcp\Server\Tool>>
     */
    protected array $tools = [
        MyAccessTool::class,
        TicketsTool::class,
        TicketAttachmentTool::class,
        TicketWriteTool::class,
        DiscordMessageTool::class,
        StaffTaskWriteTool::class,
        StaffTasksTool::class,
        ProjectsTool::class,
        ProjectWriteTool::class,
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
        OrgStockDiscontinuePreviewTool::class,
        OrgStockDiscontinueTool::class,
        FamilyRelatedProductsTool::class,
        HubOrderPlanningTool::class,
        HubShoppingListTool::class,
        PartnerRescueOrderTool::class,
        SupplierPurchaseOrderTool::class,
        ProductionRecordsTool::class,
        ProductionRecipeTool::class,
        AiChangesTool::class,
        GroupSalesTool::class,
        TradeUnitFamilySalesTool::class,
        TradeUnitSalesTool::class,
        SlowStockTool::class,
        OrderFunnelTool::class,
        CustomerConversionTool::class,
        RefundsByProductTool::class,
        PaymentMethodsTool::class,
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
