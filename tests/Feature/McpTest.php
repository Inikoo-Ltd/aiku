<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 08 Aug 2026 12:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

use App\Actions\CRM\CustomerNote\StoreCustomerNote;
use App\Actions\HumanResources\Employee\StoreEmployee;
use App\Actions\Ordering\Order\StoreOrder;
use App\Actions\SysAdmin\Guest\StoreGuest;
use App\Actions\UI\Profile\StoreProfileApiToken;
use App\Enums\Helpers\TimeSeries\TimeSeriesFrequencyEnum;
use App\Enums\HumanResources\Employee\EmployeeStateEnum;
use App\Enums\HumanResources\Employee\EmployeeTypeEnum;
use App\Enums\HumanResources\Employee\EmploymentTypeEnum;
use App\Enums\Ordering\Order\OrderStateEnum;
use App\Mcp\Resources\AikuDataGuideResource;
use App\Mcp\Servers\AikuServer;
use App\Mcp\Tools\CustomerConversionTool;
use App\Mcp\Tools\CustomerEmailPressureTool;
use App\Mcp\Tools\CustomerNotesTool;
use App\Mcp\Tools\DeliveryNotesSummaryTool;
use App\Mcp\Tools\DescribeTablesTool;
use App\Mcp\Tools\EmployeeAttendanceTool;
use App\Mcp\Tools\EmployeeDirectoryTool;
use App\Mcp\Tools\FamilySalesTool;
use App\Mcp\Tools\GroupSalesTool;
use App\Mcp\Tools\MailshotPerformanceTool;
use App\Mcp\Tools\MarginTrendTool;
use App\Mcp\Tools\MyAccessTool;
use App\Mcp\Tools\OffersOverviewTool;
use App\Mcp\Tools\OrderFunnelTool;
use App\Mcp\Tools\OrderStatusTool;
use App\Mcp\Tools\OrgFamilySalesTool;
use App\Mcp\Tools\OrgStockSalesTool;
use App\Mcp\Tools\ProductsWithoutImagesTool;
use App\Mcp\Tools\RefundsByProductTool;
use App\Mcp\Tools\ShopReviewsTool;
use App\Mcp\Tools\ShopSalesTool;
use App\Mcp\Tools\SlowStockTool;
use App\Mcp\Tools\SqlQueryTool;
use App\Mcp\Tools\StockLevelsTool;
use App\Mcp\Tools\TopProductsTool;
use App\Mcp\Tools\TradeUnitFamilySalesTool;
use App\Mcp\Tools\TradeUnitSalesTool;
use App\Mcp\Tools\WarehousePerformanceTool;
use App\Mcp\Tools\WebsiteOverviewTool;
use App\Mcp\Tools\WebTrafficTool;
use App\Models\Analytics\WebUserRequest;
use App\Models\Catalogue\ShopTimeSeries;
use App\Models\Helpers\Address;
use App\Models\HumanResources\Employee;
use App\Models\HumanResources\Timesheet;
use App\Models\Ordering\Order;
use App\Models\SysAdmin\Guest;
use App\Models\SysAdmin\McpRequest;
use Illuminate\Support\Facades\DB;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\getJson;
use function Pest\Laravel\postJson;

beforeAll(function () {
    loadDB();
});

/*
 * Every block below shares one group, one organisation, one admin user, one shop and one customer:
 * createOrganisation(), createShop() and createCustomer() all hand back the first existing row rather
 * than a fresh one. That was harmless while each block was its own file, because a file restored the
 * database before it ran. Sharing one database in sequence, a block inherits whatever the blocks above
 * it left behind.
 *
 * The tools themselves are asserted with assertOk/assertSee/assertHasErrors, so leftover rows cannot
 * push a figure too high the way they do in MarketingTest. The one thing that does leak is the shared
 * user's mcp flags: several blocks flip can_use_mcp and can_use_mcp_sql to prove the guards work, and
 * SqlQueryTool's revocation test leaves can_use_mcp off. Resetting both here, before any block builds
 * its fixtures, is what keeps that from reaching the next block.
 */
beforeEach(function () {
    list(
        $this->organisation,
        $this->user,
        $this->shop
    ) = createShop();

    $this->group    = $this->organisation->group;
    $this->customer = createCustomer($this->shop);

    app()->instance('group', $this->group);
    setPermissionsTeamId($this->group->id);

    $this->user->update(['can_use_mcp' => true, 'can_use_mcp_sql' => false]);
});

function shopDailyTimeSeries($shop): ShopTimeSeries
{
    return ShopTimeSeries::firstOrCreate([
        'shop_id'   => $shop->id,
        'frequency' => TimeSeriesFrequencyEnum::DAILY,
    ]);
}

function guestWithoutPositions($group): Guest
{
    return StoreGuest::make()->action(
        $group,
        array_merge(
            Guest::factory()->definition(),
            ['positions' => []]
        )
    );
}

describe('the aiku mcp server', function () {
    test('user without orders permission is denied', function () {
        $guest = guestWithoutPositions($this->group);

        $response = AikuServer::actingAs($guest->getUser())->tool(ShopSalesTool::class, [
            'shop' => $this->shop->slug,
            'from' => '2026-01-01',
            'to'   => '2026-12-31',
        ]);

        $response->assertHasErrors(['You do not have access to any shop.']);
    });

    test('unknown shop error lists the codes the user can query', function () {
        $response = AikuServer::actingAs($this->user)->tool(ShopSalesTool::class, [
            'shop' => 'totally-made-up',
            'from' => '2026-01-01',
            'to'   => '2026-12-31',
        ]);

        $response->assertSee("'totally-made-up' does not match any shop you can query")
            ->assertSee($this->shop->code);
    });

    test('unknown shop error reports what the user recently queried', function () {
        McpRequest::create([
            'group_id'    => $this->group->id,
            'user_id'     => $this->user->id,
            'tool'        => 'shop-sales-tool',
            'arguments'   => ['shop' => 'previously-used-code'],
            'is_error'    => false,
            'duration_ms' => 5,
            'created_at'  => now(),
        ]);

        $response = AikuServer::actingAs($this->user)->tool(ShopSalesTool::class, [
            'shop' => 'totally-made-up',
            'from' => '2026-01-01',
            'to'   => '2026-12-31',
        ]);

        $response->assertSee('This user most recently queried: previously-used-code.');
    });

    test('shop resolves by full name', function () {
        $response = AikuServer::actingAs($this->user)->tool(ShopSalesTool::class, [
            'shop' => strtoupper($this->shop->name),
            'from' => '2026-01-01',
            'to'   => '2026-12-31',
        ]);

        $response->assertOk();
    });

    test('admin user gets shop sales from time series', function () {
        $timeSeries = shopDailyTimeSeries($this->shop);
        $timeSeries->records()->updateOrCreate(
            ['period' => '2025-06-15', 'frequency' => TimeSeriesFrequencyEnum::DAILY->singleLetter()],
            [
                'from'               => '2025-06-15 00:00:00',
                'to'                 => '2025-06-15 23:59:59',
                'orders'             => 3,
                'invoices'           => 2,
                'sales_external'     => 150.50,
                'customers_invoiced' => 2,
            ]
        );

        $response = AikuServer::actingAs($this->user)->tool(ShopSalesTool::class, [
            'shop' => $this->shop->slug,
            'from' => '2025-06-01',
            'to'   => '2025-06-30',
        ]);

        $response->assertOk()
            ->assertSee('"number_orders":3')
            ->assertSee('150.5');
    });

    test('records outside the date range are excluded', function () {
        $timeSeries = shopDailyTimeSeries($this->shop);
        $timeSeries->records()->updateOrCreate(
            ['period' => '2025-03-15', 'frequency' => TimeSeriesFrequencyEnum::DAILY->singleLetter()],
            [
                'from'   => '2025-03-15 00:00:00',
                'to'     => '2025-03-15 23:59:59',
                'orders' => 7,
            ]
        );

        $response = AikuServer::actingAs($this->user)->tool(ShopSalesTool::class, [
            'shop' => $this->shop->slug,
            'from' => '2025-04-01',
            'to'   => '2025-04-30',
        ]);

        $response->assertOk()->assertSee('"number_orders":0');
    });
});

describe('mcp authentication', function () {
    test('user without can_use_mcp flag is rejected with 403', function () {
        $this->user->update(['can_use_mcp' => false]);
        $plainTextToken = $this->user->createToken('NoMcpFlag')->plainTextToken;

        postJson('/mcp/aiku', [
            'jsonrpc' => '2.0',
            'id'      => 1,
            'method'  => 'ping',
        ], [
            'Accept'        => 'application/json, text/event-stream',
            'Authorization' => 'Bearer '.$plainTextToken,
        ])->assertForbidden();
    });

    test('oauth discovery endpoints are published', function () {
        getJson('/.well-known/oauth-authorization-server')
            ->assertOk()
            ->assertJsonStructure(['issuer', 'authorization_endpoint', 'token_endpoint', 'registration_endpoint'])
            ->assertJsonPath('token_endpoint_auth_methods_supported.0', 'none');

        getJson('/.well-known/oauth-authorization-server/mcp/aiku')
            ->assertOk()
            ->assertJsonPath('token_endpoint_auth_methods_supported.0', 'none');

        getJson('/.well-known/oauth-protected-resource')
            ->assertOk()
            ->assertJsonStructure(['resource', 'authorization_servers']);
    });

    test('mcp clients can dynamically register', function () {
        postJson('/oauth/register', [
            'client_name'   => 'TestAI',
            'redirect_uris' => ['https://example.com/callback'],
        ])
            ->assertCreated()
            ->assertJsonStructure(['client_id']);
    });

    test('mcp endpoint rejects unauthenticated requests', function () {
        postJson('/mcp/aiku', [
            'jsonrpc' => '2.0',
            'id'      => 1,
            'method'  => 'ping',
        ])->assertUnauthorized();
    });

    test('web user customer tokens are rejected by mcp endpoint', function () {
        createWebsite($this->shop);
        $customer = createCustomer($this->shop);
        $webUser  = createWebUser($customer);

        $webUserToken = $webUser->createToken('customer-token')->plainTextToken;

        postJson('/mcp/aiku', [
            'jsonrpc' => '2.0',
            'id'      => 1,
            'method'  => 'ping',
        ], [
            'Accept'        => 'application/json, text/event-stream',
            'Authorization' => 'Bearer '.$webUserToken,
        ])->assertUnauthorized();
    });

    test('mcp endpoint still accepts sanctum tokens', function () {
        $plainTextToken = StoreProfileApiToken::make()->handle($this->user, 'McpTest')['token'];

        postJson('/mcp/aiku', [
            'jsonrpc' => '2.0',
            'id'      => 1,
            'method'  => 'ping',
        ], [
            'Accept'        => 'application/json, text/event-stream',
            'Authorization' => 'Bearer '.$plainTextToken,
        ])->assertOk();
    });

    test('tool calls are logged with user tool and arguments', function () {
        $plainTextToken = StoreProfileApiToken::make()->handle($this->user, 'McpLogTest')['token'];

        $countBefore = McpRequest::count();

        postJson('/mcp/aiku', [
            'jsonrpc' => '2.0',
            'id'      => 2,
            'method'  => 'tools/call',
            'params'  => [
                'name'      => 'shop-sales',
                'arguments' => ['shop' => $this->shop->slug, 'from' => '2026-01-01', 'to' => '2026-01-31'],
            ],
        ], [
            'Accept'        => 'application/json, text/event-stream',
            'Authorization' => 'Bearer '.$plainTextToken,
        ])->assertOk();

        expect(McpRequest::count())->toBe($countBefore + 1);

        $logged = McpRequest::latest('id')->first();
        expect($logged->user_id)->toBe($this->user->id)
            ->and($logged->tool)->toBe('shop-sales')
            ->and($logged->arguments['shop'])->toBe($this->shop->slug);
    });

    test('ping requests are not logged', function () {
        $plainTextToken = StoreProfileApiToken::make()->handle($this->user, 'McpPingTest')['token'];

        $countBefore = McpRequest::count();

        postJson('/mcp/aiku', [
            'jsonrpc' => '2.0',
            'id'      => 3,
            'method'  => 'ping',
        ], [
            'Accept'        => 'application/json, text/event-stream',
            'Authorization' => 'Bearer '.$plainTextToken,
        ])->assertOk();

        expect(McpRequest::count())->toBe($countBefore);
    });
});

describe('the schema guide', function () {
    beforeEach(function () {
        $this->user->update(['can_use_mcp_sql' => true]);

        config()->set('mcp.sql_read_only_user', 'aiku_read_only_test');
    });

    test('sql tools refuse to run without a dedicated read only database user', function () {
        config()->set('mcp.sql_read_only_user', null);

        $response = AikuServer::actingAs($this->user)->tool(DescribeTablesTool::class, [
            'search' => 'invoices',
        ]);

        $response->assertHasErrors(['SQL access is disabled: this environment has no dedicated read-only database user configured.']);
    });

    test('a user without sql access cannot even reach the tool', function () {
        $this->user->update(['can_use_mcp_sql' => false]);

        actingAs($this->user);

        expect((new DescribeTablesTool())->shouldRegister(new Laravel\Mcp\Request()))->toBeFalse();

        $this->user->update(['can_use_mcp_sql' => true]);

        expect((new DescribeTablesTool())->shouldRegister(new Laravel\Mcp\Request()))->toBeTrue();
    });

    test('search finds tables by partial name', function () {
        $response = AikuServer::actingAs($this->user)->tool(DescribeTablesTool::class, [
            'search' => 'shop_time_series',
        ]);

        $response->assertOk()
            ->assertSee('shop_time_series')
            ->assertSee('shop_time_series_records');
    });

    test('describe returns columns and foreign keys', function () {
        $response = AikuServer::actingAs($this->user)->tool(DescribeTablesTool::class, [
            'tables' => ['invoices'],
        ]);

        $response->assertOk()
            ->assertSee('total_amount')
            ->assertSee('deleted_at')
            ->assertSee('foreign_keys');
    });

    test('describe reports enum-like column values from pg_stats', function () {
        DB::statement('ANALYZE countries');

        $response = AikuServer::actingAs($this->user)->tool(DescribeTablesTool::class, [
            'tables' => ['countries'],
        ]);

        $response->assertOk()
            ->assertSee('enum_values')
            ->assertSee('independent');
    });

    test('describe reports unknown tables instead of failing', function () {
        $response = AikuServer::actingAs($this->user)->tool(DescribeTablesTool::class, [
            'tables' => ['no_such_table_here'],
        ]);

        $response->assertOk()->assertSee('not_found');
    });

    test('data guide resource explains the time series rule', function () {
        $response = AikuServer::resource(AikuDataGuideResource::class);

        $response->assertOk()
            ->assertSee('sales_external')
            ->assertSee('sales_intervals')
            ->assertSee('deleted_at');
    });
});

describe('the sql query tool', function () {
    beforeEach(function () {
        config(['database.connections.aiku_read_only' => config('database.connections.'.config('database.default'))]);
        config()->set('mcp.sql_read_only_user', 'aiku_read_only_test');
    });

    test('user without sql access is not offered the tool at all', function () {
        actingAs($this->user);

        expect((new SqlQueryTool())->shouldRegister(new Laravel\Mcp\Request()))->toBeFalse();

        $this->user->update(['can_use_mcp_sql' => true]);

        expect((new SqlQueryTool())->shouldRegister(new Laravel\Mcp\Request()))->toBeTrue();
    });

    test('user with sql access can run a select', function () {
        $this->user->update(['can_use_mcp_sql' => true]);

        $response = AikuServer::actingAs($this->user)->tool(SqlQueryTool::class, [
            'sql' => 'select count(*) as total from shops',
        ]);

        $response->assertOk();
    });

    test('sql access is revoked when mcp access is turned off', function () {
        $this->user->update(['can_use_mcp_sql' => true]);

        App\Actions\SysAdmin\User\UpdateUser::make()->action($this->user, ['can_use_mcp' => false]);

        expect($this->user->refresh()->can_use_mcp_sql)->toBeFalse();
    });

    test('sql access cannot be granted without mcp access', function () {
        $this->user->update(['can_use_mcp' => false]);

        App\Actions\SysAdmin\User\UpdateUser::make()->action($this->user, ['can_use_mcp_sql' => true]);

        expect($this->user->refresh()->can_use_mcp_sql)->toBeFalse();
    });

    test('non select statements are rejected', function () {
        $this->user->update(['can_use_mcp_sql' => true]);

        $response = AikuServer::actingAs($this->user)->tool(SqlQueryTool::class, [
            'sql' => 'update shops set name = \'x\'',
        ]);

        $response->assertHasErrors(['Only SELECT statements are allowed.']);
    });

    test('multiple statements are rejected', function () {
        $this->user->update(['can_use_mcp_sql' => true]);

        $response = AikuServer::actingAs($this->user)->tool(SqlQueryTool::class, [
            'sql' => 'select 1; drop table shops',
        ]);

        $response->assertHasErrors(['Only a single statement is allowed.']);
    });

    test('database parameter selects the nightowl connection', function () {
        $this->user->update(['can_use_mcp_sql' => true]);

        config(['database.connections.nightowl' => config('database.connections.'.config('database.default'))]);

        $response = AikuServer::actingAs($this->user)->tool(SqlQueryTool::class, [
            'sql'      => 'select 1 as one',
            'database' => 'nightowl',
        ]);

        $response->assertOk();
    });

    test('unknown database is rejected', function () {
        $this->user->update(['can_use_mcp_sql' => true]);

        $response = AikuServer::actingAs($this->user)->tool(SqlQueryTool::class, [
            'sql'      => 'select 1',
            'database' => 'prod',
        ]);

        $response->assertHasErrors();
    });
});

describe('the my access tool', function () {
    test('an admin sees the slugs they can query', function () {
        $response = AikuServer::actingAs($this->user)->tool(MyAccessTool::class, []);

        $response->assertOk()
            ->assertSee($this->shop->slug)
            ->assertSee($this->organisation->slug);
    });

    test('a user with no permissions sees empty lists rather than an error', function () {
        $guest = guestWithoutPositions($this->group);

        $response = AikuServer::actingAs($guest->getUser())->tool(MyAccessTool::class, []);

        $response->assertOk()
            ->assertSee('"shops":[]')
            ->assertSee('"organisations":[]');
    });

    test('tools accept a code as well as a slug', function () {
        $response = AikuServer::actingAs($this->user)->tool(ShopSalesTool::class, [
            'shop' => strtoupper($this->shop->code),
            'from' => '2026-01-01',
            'to'   => '2026-01-31',
        ]);

        $response->assertOk()->assertSee($this->shop->name);
    });

    test('sql users are offered only the database tools', function () {
        $this->user->update(['can_use_mcp_sql' => true]);

        actingAs($this->user);
        $mcpRequest = new Laravel\Mcp\Request();

        expect((new ShopSalesTool())->shouldRegister($mcpRequest))->toBeFalse()
            ->and((new SqlQueryTool())->shouldRegister($mcpRequest))->toBeTrue();

        $this->user->update(['can_use_mcp_sql' => false]);

        expect((new ShopSalesTool())->shouldRegister($mcpRequest))->toBeTrue()
            ->and((new SqlQueryTool())->shouldRegister($mcpRequest))->toBeFalse();
    });
});

describe('catalogue health tools', function () {
    test('user without products permission is denied on ProductsWithoutImagesTool', function () {
        $guest = guestWithoutPositions($this->group);

        $response = AikuServer::actingAs($guest->getUser())->tool(ProductsWithoutImagesTool::class, [
            'shop' => $this->shop->slug,
        ]);

        $response->assertHasErrors(['You do not have access to any shop.']);
    });

    test('admin gets products without images', function () {
        [$orgStocks, $product] = createProduct($this->shop);

        $product->update(['image_id' => null]);

        $response = AikuServer::actingAs($this->user)->tool(ProductsWithoutImagesTool::class, [
            'shop' => $this->shop->slug,
        ]);

        $response->assertOk()
            ->assertSee('"total_without_images"');
    });

    test('FamilySalesTool returns families over date range', function () {
        createProduct($this->shop);

        $response = AikuServer::actingAs($this->user)->tool(FamilySalesTool::class, [
            'shop' => $this->shop->slug,
            'from' => '2026-01-01',
            'to'   => '2026-12-31',
        ]);

        $response->assertOk()
            ->assertSee('"families"');
    });

    test('FamilySalesTool with invalid date range fails validation', function () {
        $response = AikuServer::actingAs($this->user)->tool(FamilySalesTool::class, [
            'shop' => $this->shop->slug,
            'from' => '2026-12-31',
            'to'   => '2026-01-01',
        ]);

        $response->assertHasErrors();
    });

    test('admin gets offers overview with status all', function () {
        createProduct($this->shop);

        $response = AikuServer::actingAs($this->user)->tool(OffersOverviewTool::class, [
            'shop'   => $this->shop->slug,
            'status' => 'all',
        ]);

        $response->assertOk()
            ->assertSee('"offers"');
    });
});

describe('crm email tools', function () {
    test('user without marketing view permission is denied on MailshotPerformanceTool', function () {
        $guest = guestWithoutPositions($this->group);

        $response = AikuServer::actingAs($guest->getUser())->tool(MailshotPerformanceTool::class, [
            'shop' => $this->shop->slug,
        ]);

        $response->assertHasErrors(['You do not have access to any shop.']);
    });

    test('user without crm view permission is denied on CustomerEmailPressureTool', function () {
        $guest = guestWithoutPositions($this->group);

        $response = AikuServer::actingAs($guest->getUser())->tool(CustomerEmailPressureTool::class, [
            'shop' => $this->shop->slug,
            'from' => '2026-01-01',
            'to'   => '2026-12-31',
        ]);

        $response->assertHasErrors(['You do not have access to any shop.']);
    });

    test('admin user gets mailshot performance with no data', function () {
        $response = AikuServer::actingAs($this->user)->tool(MailshotPerformanceTool::class, [
            'shop' => $this->shop->slug,
        ]);

        $response->assertOk()
            ->assertSee('"mailshots":[]');
    });

    test('admin user gets customer email pressure with date range', function () {
        $response = AikuServer::actingAs($this->user)->tool(CustomerEmailPressureTool::class, [
            'shop' => $this->shop->slug,
            'from' => '2026-01-01',
            'to'   => '2026-12-31',
        ]);

        $response->assertOk()
            ->assertSee('"total_emails":0')
            ->assertSee('"customers_reached":0');
    });
});

describe('group sales tools', function () {
    test('user without group reports permission is denied', function () {
        $guest = guestWithoutPositions($this->group);

        $response = AikuServer::actingAs($guest->getUser())->tool(GroupSalesTool::class, [
            'from' => '2026-01-01',
            'to'   => '2026-12-31',
        ]);

        $response->assertHasErrors(['Permission denied.']);
    });

    test('admin gets group wide sales', function () {
        $response = AikuServer::actingAs($this->user)->tool(GroupSalesTool::class, [
            'from' => '2026-01-01',
            'to'   => '2026-12-31',
        ]);

        $response->assertOk()
            ->assertSee('"net_sales"')
            ->assertSee('"customers_invoiced"');
    });

    test('admin gets trade unit family sales', function () {
        $response = AikuServer::actingAs($this->user)->tool(TradeUnitFamilySalesTool::class, [
            'from' => '2026-01-01',
            'to'   => '2026-12-31',
            'sort' => 'worst',
        ]);

        $response->assertOk()->assertSee('"sort":"worst"');
    });

    test('admin gets trade unit sales', function () {
        $response = AikuServer::actingAs($this->user)->tool(TradeUnitSalesTool::class, [
            'from' => '2026-01-01',
            'to'   => '2026-12-31',
        ]);

        $response->assertOk()->assertSee('"trade_units"');
    });
});

describe('org sales tools', function () {
    test('user without accounting permission is denied on org family sales', function () {
        $guest = guestWithoutPositions($this->group);

        $response = AikuServer::actingAs($guest->getUser())->tool(OrgFamilySalesTool::class, [
            'organisation' => $this->organisation->slug,
            'from'         => '2026-01-01',
            'to'           => '2026-12-31',
        ]);

        $response->assertHasErrors(['You do not have access to any organisation.']);
    });

    test('admin gets org family sales', function () {
        $response = AikuServer::actingAs($this->user)->tool(OrgFamilySalesTool::class, [
            'organisation' => $this->organisation->slug,
            'from'         => '2026-01-01',
            'to'           => '2026-12-31',
            'sort'         => 'worst',
        ]);

        $response->assertOk()
            ->assertSee('"sort":"worst"')
            ->assertSee('"families"');
    });

    test('admin gets org stock sales with stock on hand', function () {
        $response = AikuServer::actingAs($this->user)->tool(OrgStockSalesTool::class, [
            'organisation' => $this->organisation->slug,
            'from'         => '2026-01-01',
            'to'           => '2026-12-31',
        ]);

        $response->assertOk()
            ->assertSee('"sort":"best"')
            ->assertSee('"stocks"');
    });

    test('invalid sort fails validation', function () {
        $response = AikuServer::actingAs($this->user)->tool(OrgStockSalesTool::class, [
            'organisation' => $this->organisation->slug,
            'from'         => '2026-01-01',
            'to'           => '2026-12-31',
            'sort'         => 'sideways',
        ]);

        $response->assertHasErrors();
    });
});

describe('hr tools', function () {
    test('user without hr permission is denied', function () {
        $guest = guestWithoutPositions($this->group);

        $response = AikuServer::actingAs($guest->getUser())->tool(EmployeeDirectoryTool::class, [
            'organisation' => $this->organisation->slug,
            'query'        => 'x',
        ]);

        $response->assertHasErrors(['You do not have access to any organisation.']);
    });

    test('admin can search employee directory', function () {
        StoreEmployee::make()->action(
            $this->organisation,
            array_merge(
                Employee::factory()->definition(),
                [
                    'contact_name'    => 'John Developer',
                    'worker_number'   => 'EMP-'.uniqid(),
                    'job_title'       => 'Software Engineer',
                    'work_email'      => uniqid('john').'@example.com',
                    'state'           => EmployeeStateEnum::WORKING,
                    'type'            => EmployeeTypeEnum::EMPLOYEE,
                    'employment_type' => EmploymentTypeEnum::FULL_TIME,
                ]
            ),
            audit: false
        );

        $response = AikuServer::actingAs($this->user)->tool(EmployeeDirectoryTool::class, [
            'organisation' => $this->organisation->slug,
            'query'        => 'John',
        ]);

        $response->assertOk()->assertSee('John Developer');
    });

    test('directory response never contains salary', function () {
        StoreEmployee::make()->action(
            $this->organisation,
            array_merge(
                Employee::factory()->definition(),
                [
                    'contact_name'    => 'Jane Manager',
                    'worker_number'   => 'EMP-'.uniqid(),
                    'job_title'       => 'Manager',
                    'work_email'      => uniqid('jane').'@example.com',
                    'state'           => EmployeeStateEnum::WORKING,
                    'type'            => EmployeeTypeEnum::EMPLOYEE,
                    'employment_type' => EmploymentTypeEnum::FULL_TIME,
                    'salary'          => ['amount' => 50000, 'currency' => 'USD'],
                ]
            ),
            audit: false
        );

        $response = AikuServer::actingAs($this->user)->tool(EmployeeDirectoryTool::class, [
            'organisation' => $this->organisation->slug,
            'query'        => 'Jane',
        ]);

        $response->assertOk()
            ->assertSee('Jane Manager')
            ->assertDontSee('salary')
            ->assertDontSee('date_of_birth');
    });

    test('admin can view employee attendance', function () {
        $employee = StoreEmployee::make()->action(
            $this->organisation,
            array_merge(
                Employee::factory()->definition(),
                [
                    'contact_name'    => 'Alice Worker',
                    'worker_number'   => 'EMP-'.uniqid(),
                    'state'           => EmployeeStateEnum::WORKING,
                    'type'            => EmployeeTypeEnum::EMPLOYEE,
                    'employment_type' => EmploymentTypeEnum::FULL_TIME,
                ]
            ),
            audit: false
        );

        Timesheet::create([
            'group_id'                  => $this->organisation->group_id,
            'organisation_id'           => $this->organisation->id,
            'date'                      => '2026-07-20',
            'subject_type'              => 'Employee',
            'subject_id'                => $employee->id,
            'subject_name'              => $employee->contact_name,
            'working_duration'          => 28800,
            'breaks_duration'           => 3600,
            'total_duration'            => 32400,
            'number_time_trackers'      => 1,
            'number_open_time_trackers' => 0,
        ]);

        $response = AikuServer::actingAs($this->user)->tool(EmployeeAttendanceTool::class, [
            'organisation' => $this->organisation->slug,
            'employee'     => $employee->slug,
            'from'         => '2026-07-20',
            'to'           => '2026-07-22',
        ]);

        $response->assertOk()
            ->assertSee('Alice Worker')
            ->assertSee('"days_with_timesheet":1')
            ->assertSee('"total_clocked":8');
    });

    test('attendance flags a day the employee has not clocked out of', function () {
        $employee = StoreEmployee::make()->action(
            $this->organisation,
            array_merge(
                Employee::factory()->definition(),
                [
                    'contact_name'    => 'Bob OpenShift',
                    'type'            => EmployeeTypeEnum::EMPLOYEE,
                    'employment_type' => EmploymentTypeEnum::FULL_TIME,
                ]
            ),
            audit: false
        );

        Timesheet::create([
            'group_id'                  => $this->organisation->group_id,
            'organisation_id'           => $this->organisation->id,
            'date'                      => '2026-07-20',
            'subject_type'              => 'Employee',
            'subject_id'                => $employee->id,
            'subject_name'              => $employee->contact_name,
            'working_duration'          => 0,
            'breaks_duration'           => 0,
            'total_duration'            => 0,
            'number_time_trackers'      => 1,
            'number_open_time_trackers' => 1,
        ]);

        $response = AikuServer::actingAs($this->user)->tool(EmployeeAttendanceTool::class, [
            'organisation' => $this->organisation->slug,
            'employee'     => $employee->slug,
            'from'         => '2026-07-20',
            'to'           => '2026-07-22',
        ]);

        $response->assertOk()
            ->assertSee('UNDERSTATED')
            ->assertSee('2026-07-20');
    });

    test('attendance refuses to let unreconciled hours pass silently', function () {
        $employee = StoreEmployee::make()->action(
            $this->organisation,
            array_merge(
                Employee::factory()->definition(),
                [
                    'contact_name'    => 'Carol NoTrackers',
                    'type'            => EmployeeTypeEnum::EMPLOYEE,
                    'employment_type' => EmploymentTypeEnum::FULL_TIME,
                ]
            ),
            audit: false
        );

        Timesheet::create([
            'group_id'                  => $this->organisation->group_id,
            'organisation_id'           => $this->organisation->id,
            'date'                      => '2026-07-20',
            'subject_type'              => 'Employee',
            'subject_id'                => $employee->id,
            'subject_name'              => $employee->contact_name,
            'working_duration'          => 28800,
            'breaks_duration'           => 0,
            'total_duration'            => 28800,
            'number_time_trackers'      => 0,
            'number_open_time_trackers' => 0,
        ]);

        $response = AikuServer::actingAs($this->user)->tool(EmployeeAttendanceTool::class, [
            'organisation' => $this->organisation->slug,
            'employee'     => $employee->slug,
            'from'         => '2026-07-20',
            'to'           => '2026-07-22',
        ]);

        $response->assertOk()->assertSee('DOES NOT RECONCILE');
    });

    test('employee not found returns error', function () {
        $response = AikuServer::actingAs($this->user)->tool(EmployeeAttendanceTool::class, [
            'organisation' => $this->organisation->slug,
            'employee'     => 'nonexistent-employee',
            'from'         => '2026-07-20',
            'to'           => '2026-07-22',
        ]);

        $response->assertHasErrors(['Employee not found.']);
    });
});

describe('insight tools', function () {
    test('guest without permissions is denied on every insight tool', function (string $tool) {
        $guest = guestWithoutPositions($this->group);

        $response = AikuServer::actingAs($guest->getUser())->tool($tool, [
            'shop' => $this->shop->slug,
            'from' => '2026-01-01',
            'to'   => '2026-12-31',
        ]);

        $response->assertHasErrors(['You do not have access to any shop.']);
    })->with([
        SlowStockTool::class,
        OrderFunnelTool::class,
        CustomerConversionTool::class,
        RefundsByProductTool::class,
        MarginTrendTool::class,
    ]);

    test('admin can run every insight tool', function (string $tool) {
        $response = AikuServer::actingAs($this->user)->tool($tool, [
            'shop' => $this->shop->slug,
            'from' => '2026-01-01',
            'to'   => '2026-12-31',
        ]);

        $response->assertOk();
    })->with([
        SlowStockTool::class,
        OrderFunnelTool::class,
        CustomerConversionTool::class,
        RefundsByProductTool::class,
        MarginTrendTool::class,
    ]);

    /* The only count assertion in this file, and the only reason no other block here may leave a
       CREATING order behind on the shared customer. */
    test('order funnel counts an abandoned basket', function () {
        $billingAddress  = new Address(Address::factory()->definition());
        $deliveryAddress = new Address(Address::factory()->definition());

        $modelData = Order::factory()->definition();
        data_set($modelData, 'billing_address', $billingAddress);
        data_set($modelData, 'delivery_address', $deliveryAddress);

        $order = StoreOrder::make()->action($this->customer, $modelData);
        $order->update([
            'state'      => OrderStateEnum::CREATING,
            'net_amount' => 99.50,
            'date'       => '2026-06-15',
        ]);

        $response = AikuServer::actingAs($this->user)->tool(OrderFunnelTool::class, [
            'shop' => $this->shop->slug,
            'from' => '2026-06-01',
            'to'   => '2026-06-30',
        ]);

        $response->assertOk()
            ->assertSee('"abandoned_baskets":1')
            ->assertSee('99.5');
    });
});

describe('reviews and notes tools', function () {
    test('user without crm permission is denied on reviews', function () {
        $guest = guestWithoutPositions($this->group);

        $response = AikuServer::actingAs($guest->getUser())->tool(ShopReviewsTool::class, [
            'shop' => $this->shop->slug,
        ]);

        $response->assertHasErrors(['You do not have access to any shop.']);
    });

    test('admin gets shop reviews overview', function () {
        $response = AikuServer::actingAs($this->user)->tool(ShopReviewsTool::class, [
            'shop' => $this->shop->slug,
        ]);

        $response->assertOk()->assertSee('number_reviews');
    });

    test('admin can read customer notes', function () {
        actingAs($this->user);

        $noteText = 'Prefers delivery on Fridays '.uniqid();
        StoreCustomerNote::make()->action($this->customer, [
            'note' => $noteText,
        ]);

        $response = AikuServer::actingAs($this->user)->tool(CustomerNotesTool::class, [
            'shop'     => $this->shop->slug,
            'customer' => $this->customer->slug,
        ]);

        $response->assertOk()->assertSee('Prefers delivery on Fridays');
    });

    test('notes search filters results', function () {
        $response = AikuServer::actingAs($this->user)->tool(CustomerNotesTool::class, [
            'shop'     => $this->shop->slug,
            'customer' => $this->customer->slug,
            'search'   => 'zzz-no-match-'.uniqid(),
        ]);

        $response->assertOk()->assertSee('"notes":[]');
    });

    test('unknown customer returns error', function () {
        $response = AikuServer::actingAs($this->user)->tool(CustomerNotesTool::class, [
            'shop'     => $this->shop->slug,
            'customer' => 'no-such-customer',
        ]);

        $response->assertHasErrors(['Customer not found.']);
    });
});

describe('shop catalogue tools', function () {
    test('user without products permission is denied', function () {
        $guest = guestWithoutPositions($this->group);

        $response = AikuServer::actingAs($guest->getUser())->tool(TopProductsTool::class, [
            'shop' => $this->shop->slug,
            'from' => '2026-01-01',
            'to'   => '2026-12-31',
        ]);

        $response->assertHasErrors(['You do not have access to any shop.']);
    });

    test('admin gets top products', function () {
        $response = AikuServer::actingAs($this->user)->tool(TopProductsTool::class, [
            'shop'  => $this->shop->slug,
            'from'  => '2026-01-01',
            'to'    => '2026-12-31',
            'limit' => 10,
        ]);

        $response->assertOk();
    });

    test('order status by reference', function () {
        $billingAddress  = new Address(Address::factory()->definition());
        $deliveryAddress = new Address(Address::factory()->definition());

        $modelData = Order::factory()->definition();
        data_set($modelData, 'billing_address', $billingAddress);
        data_set($modelData, 'delivery_address', $deliveryAddress);

        $order = StoreOrder::make()->action($this->customer, $modelData);
        $order->update([
            'state'      => OrderStateEnum::SUBMITTED,
            'net_amount' => 150.50,
            'date'       => '2026-06-15',
        ]);

        $response = AikuServer::actingAs($this->user)->tool(OrderStatusTool::class, [
            'shop'      => $this->shop->slug,
            'reference' => $order->reference,
        ]);

        $response->assertOk()
            ->assertSee($order->reference);
    });

    test('order status for unknown reference errors', function () {
        $response = AikuServer::actingAs($this->user)->tool(OrderStatusTool::class, [
            'shop'      => $this->shop->slug,
            'reference' => 'nope-999',
        ]);

        $response->assertHasErrors(['Order not found.']);
    });
});

describe('warehouse tools', function () {
    beforeEach(function () {
        $this->warehouse = createWarehouse();
    });

    test('user without stocks permission is denied', function () {
        $guest = guestWithoutPositions($this->group);

        $response = AikuServer::actingAs($guest->getUser())->tool(StockLevelsTool::class, [
            'warehouse' => $this->warehouse->slug,
            'query'     => 'x',
        ]);

        $response->assertHasErrors(['You do not have access to any warehouse.']);
    });

    test('admin user can search stock levels', function () {
        $response = AikuServer::actingAs($this->user)->tool(StockLevelsTool::class, [
            'warehouse' => $this->warehouse->slug,
            'query'     => 'anything',
        ]);

        $response->assertOk();
    });

    test('admin user gets delivery note summary', function () {
        $response = AikuServer::actingAs($this->user)->tool(DeliveryNotesSummaryTool::class, [
            'warehouse' => $this->warehouse->slug,
            'from'      => '2026-01-01',
            'to'        => '2026-12-31',
        ]);

        $response->assertOk();
    });

    test('admin user gets warehouse performance in every breakdown', function (string $breakdown) {
        $response = AikuServer::actingAs($this->user)->tool(WarehousePerformanceTool::class, [
            'warehouse' => $this->warehouse->slug,
            'from'      => '2026-01-01',
            'to'        => '2026-12-31',
            'breakdown' => $breakdown,
        ]);

        $response->assertOk();
    })->with(['summary', 'pickers', 'packers', 'hourly']);
});

describe('web tools', function () {
    test('user without web permission is denied on website overview', function () {
        $guest = guestWithoutPositions($this->group);

        $response = AikuServer::actingAs($guest->getUser())->tool(WebsiteOverviewTool::class, [
            'shop' => $this->shop->slug,
        ]);

        $response->assertHasErrors(['You do not have access to any shop.']);
    });

    test('admin user gets website overview', function () {
        $website = createWebsite($this->shop);

        $response = AikuServer::actingAs($this->user)->tool(WebsiteOverviewTool::class, [
            'shop' => $this->shop->slug,
        ]);

        $response->assertOk()
            ->assertSee('"website_name":"'.$website->name.'"');
    });

    test('admin user gets web traffic data', function () {
        $website = createWebsite($this->shop);
        $webUser = createWebUser(createCustomer($this->shop));

        WebUserRequest::create([
            'group_id'     => $this->group->id,
            'website_id'   => $website->id,
            'web_user_id'  => $webUser->id,
            'date'         => '2026-06-15',
            'route_name'   => 'test',
            'route_params' => '{}',
            'location'     => '{}',
            'device'       => 'desktop',
            'os'           => 'Linux',
            'browser'      => 'Chrome',
            'ip_address'   => '127.0.0.1',
        ]);

        $response = AikuServer::actingAs($this->user)->tool(WebTrafficTool::class, [
            'shop' => $this->shop->slug,
            'from' => '2026-01-01',
            'to'   => '2026-12-31',
        ]);

        $response->assertOk()
            ->assertSee('"website_name":"'.$website->name.'"');
    });
});
