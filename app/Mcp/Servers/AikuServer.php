<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 22 Jul 2026 00:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Mcp\Servers;

use App\Mcp\Tools\CustomerEmailPressureTool;
use App\Mcp\Tools\CustomerNotesTool;
use App\Mcp\Tools\DeliveryNotesSummaryTool;
use App\Mcp\Tools\EmployeeAttendanceTool;
use App\Mcp\Tools\EmployeeDirectoryTool;
use App\Mcp\Tools\FamilySalesTool;
use App\Mcp\Tools\MailshotPerformanceTool;
use App\Mcp\Tools\OffersOverviewTool;
use App\Mcp\Tools\OrderStatusTool;
use App\Mcp\Tools\ProductsWithoutImagesTool;
use App\Mcp\Tools\ShopReviewsTool;
use App\Mcp\Tools\ShopSalesTool;
use App\Mcp\Tools\StockLevelsTool;
use App\Mcp\Tools\TopProductsTool;
use App\Mcp\Tools\WebsiteOverviewTool;
use App\Mcp\Tools\WebTrafficTool;
use Laravel\Mcp\Server;
use Laravel\Mcp\Server\Attributes\Instructions;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Version;

#[Name('Aiku')]
#[Version('1.0.0')]
#[Instructions('Read-only access to Aiku commerce data. Every tool is scoped by the authenticated user\'s permissions: a tool call against a shop the user cannot view returns a permission error.')]
class AikuServer extends Server
{
    /**
     * @var array<int, class-string<\Laravel\Mcp\Server\Tool>>
     */
    protected array $tools = [
        ShopSalesTool::class,
        TopProductsTool::class,
        OrderStatusTool::class,
        StockLevelsTool::class,
        DeliveryNotesSummaryTool::class,
        EmployeeDirectoryTool::class,
        EmployeeAttendanceTool::class,
        WebsiteOverviewTool::class,
        WebTrafficTool::class,
        ProductsWithoutImagesTool::class,
        FamilySalesTool::class,
        OffersOverviewTool::class,
        MailshotPerformanceTool::class,
        CustomerEmailPressureTool::class,
        ShopReviewsTool::class,
        CustomerNotesTool::class,
    ];
}
