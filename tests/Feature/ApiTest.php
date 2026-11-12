<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 01 Jul 2026 00:00:00 Central Indonesia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

/** @noinspection PhpUnhandledExceptionInspection */

namespace Tests\Feature;

use App\Actions\Accounting\Invoice\StoreInvoice;
use App\Actions\Goods\Stock\StoreStock;
use App\Actions\Inventory\Location\StoreLocation;
use App\Actions\Inventory\LocationOrgStock\DisassociateEmptyLocationOrgStocks;
use App\Actions\Inventory\LocationOrgStock\StoreLocationOrgStock;
use App\Actions\Inventory\OrgStock\StoreOrgStock;
use App\Actions\Inventory\WarehouseArea\StoreWarehouseArea;
use App\Enums\Inventory\LocationStock\LocationStockTypeEnum;
use App\Models\Goods\Stock;
use App\Enums\Inventory\OrgStockMovement\OrgStockMovementReasonEnum;
use App\Enums\Inventory\OrgStockMovement\OrgStockMovementTypeEnum;
use App\Models\Inventory\Location;
use App\Models\Inventory\LocationOrgStock;
use App\Models\Inventory\OrgStock;
use App\Models\Inventory\OrgStockMovement;
use App\Models\Inventory\Warehouse;
use App\Models\Inventory\WarehouseArea;
use App\Actions\Ordering\Order\StoreOrder;
use App\Actions\Ordering\Order\UpdateState\SubmitOrder;
use App\Actions\HumanResources\AttendanceAdjustment\StoreAttendanceAdjustment;
use App\Actions\HumanResources\ClockingMachine\StoreClockingMachine;
use App\Actions\HumanResources\ClockingMachine\StoreClockingMachineQRCode;
use App\Actions\HumanResources\EmployeeContract\StoreEmployeeContract;
use App\Actions\HumanResources\Holiday\StoreHoliday;
use App\Actions\HumanResources\Leave\StoreLeaveType;
use App\Actions\HumanResources\Overtime\StoreOvertimeType;
use App\Actions\HumanResources\WorkSchedule\StoreWorkSchedule;
use App\Actions\HumanResources\Workplace\StoreWorkplace;
use App\Actions\SysAdmin\User\SetUserAuthorisedModels;
use App\Actions\SysAdmin\User\StoreUserFromEmployee;
use App\Enums\HumanResources\Attendance\AttendanceAdjustmentStatusEnum;
use App\Enums\HumanResources\ClockingMachine\ClockingMachineTypeEnum;
use App\Enums\HumanResources\Employee\EmployeeStateEnum;
use App\Enums\HumanResources\Holiday\HolidayTypeEnum;
use App\Enums\HumanResources\Leave\LeaveCategoryEnum;
use App\Enums\HumanResources\Overtime\OvertimeCategoryEnum;
use App\Enums\HumanResources\Overtime\OvertimeCompensationTypeEnum;
use App\Enums\HumanResources\Workplace\WorkplaceTypeEnum;
use App\Models\HumanResources\AttendanceAdjustment;
use App\Models\HumanResources\ClockingMachineQRCode;
use App\Models\HumanResources\Employee;
use App\Models\HumanResources\Holiday;
use App\Models\HumanResources\Leave;
use App\Models\HumanResources\LeaveType;
use App\Models\HumanResources\OvertimeRequest;
use App\Models\HumanResources\OvertimeType;
use App\Models\HumanResources\RestrictedPeriod;
use App\Models\HumanResources\Timesheet;
use App\Models\SysAdmin\Organisation;
use App\Enums\SysAdmin\Authorisation\RolesEnum;
use App\Models\Accounting\Invoice;
use App\Models\Chat\ChatAiAccountUsageSample;
use App\Models\Ordering\Order;
use App\Models\SysAdmin\Group;
use App\Models\SysAdmin\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Laravel\Sanctum\PersonalAccessToken;
use Laravel\Sanctum\Sanctum;
use PragmaRX\Google2FAQRCode\Google2FA;

use function Pest\Laravel\deleteJson;
use function Pest\Laravel\get;
use function Pest\Laravel\getJson;
use function Pest\Laravel\patchJson;
use function Pest\Laravel\post;
use function Pest\Laravel\postJson;

require_once __DIR__.'/../Support/MobileApiHelpers.php';

/**
 * @return array{0: User, 1: Warehouse}
 */
function actingAsMobileLocationsViewer(Group $group): array
{
    config(['app.mobile_app_keys' => ['mobile-test-key']]);
    Auth::shouldUse('web');
    $warehouse = createWarehouse();
    $user      = createMobileAppUser($group);
    $user->assignRole(RolesEnum::getRoleName(RolesEnum::DISPATCH_CLERK->value, $warehouse));
    SetUserAuthorisedModels::run($user);
    Sanctum::actingAs($user, ['mobile']);

    return [$user, $warehouse];
}

/**
 * @return array{0: WarehouseArea, 1: Location, 2: Location, 3: string}
 */
function createMobileLocationsFixture(Warehouse $warehouse): array
{
    $prefix = 'MB'.Str::upper(Str::random(5));
    $area   = StoreWarehouseArea::make()->action($warehouse, array_merge(WarehouseArea::factory()->definition(), ['code' => $prefix.'A']));

    $stocked = StoreLocation::make()->action($area, ['code' => $prefix.'-01', 'max_weight' => 120, 'max_volume' => 1.2]);
    $empty   = StoreLocation::make()->action($area, ['code' => $prefix.'-02']);

    $stock    = StoreStock::make()->action($warehouse->group, Stock::factory()->definition());
    $orgStock = StoreOrgStock::make()->action($warehouse->organisation, $stock);
    StoreLocationOrgStock::make()->action(
        orgStock: $orgStock,
        location: $stocked,
        modelData: ['quantity' => 30, 'type' => LocationStockTypeEnum::PICKING, 'settings' => ['min_stock' => 12, 'max_stock' => 48], 'fetched_at' => now()],
        strict: false
    );

    return [$area, $stocked->refresh(), $empty->refresh(), $prefix];
}

/**
 * @return array{0: Location, 1: OrgStock}
 */
function createMobileEmptyLocationLink(Warehouse $warehouse, Location $location): array
{
    $stock    = StoreStock::make()->action($warehouse->group, Stock::factory()->definition());
    $orgStock = StoreOrgStock::make()->action($warehouse->organisation, $stock);
    StoreLocationOrgStock::make()->action(
        orgStock: $orgStock,
        location: $location,
        modelData: ['quantity' => 0, 'type' => LocationStockTypeEnum::STORING, 'fetched_at' => now()],
        strict: false
    );
    OrgStockMovement::where('org_stock_id', $orgStock->id)->update(['date' => now()->subDays(2)]);
    OrgStock::whereKey($orgStock->id)->update(['picking_location_id' => null, 'picking_dropshipping_location_id' => null]);

    return [$location, $orgStock];
}

/**
 * @return array{0: \App\Models\SysAdmin\User, 1: Warehouse}
 */
function actingAsMobileWarehouseUser(Group $group, RolesEnum $role): array
{
    [$user, $warehouse] = actingAsMobileLocationsViewer($group);
    grantMobileWarehouseRole($user, $warehouse, $role);

    return [$user, $warehouse];
}

function grantMobileWarehouseRole(\App\Models\SysAdmin\User $user, Warehouse $warehouse, RolesEnum $role): void
{
    $user->assignRole(RolesEnum::getRoleName($role->value, $warehouse));
    SetUserAuthorisedModels::run($user);
    Sanctum::actingAs($user->refresh(), ['mobile']);
}

beforeAll(function () {
    loadDB();
});

beforeEach(
    /**
     * @throws \Throwable
     */
    function () {
        list(
            $this->organisation,
            $this->user,
            $this->shop
        ) = createShop();
        $this->customer = createCustomer($this->shop);
        $this->group    = $this->organisation->group;

        Sanctum::actingAs($this->user);
    }
);

test('get api profile', function () {
    $response = getJson(route('grp.api.profile'));
    $response->assertOk();
    $response->assertJsonStructure([
        'data' => ['id', 'username', 'contact_name', 'email'],
    ]);
});

test('api profile lists the user pseudo job positions', function () {
    $jobPosition = \App\Models\HumanResources\JobPosition::query()->where('group_id', $this->group->id)->whereNull('organisation_id')->orderBy('id')->firstOrFail();
    $this->user->pseudoJobPositions()->syncWithoutDetaching([$jobPosition->id => ['group_id' => $this->group->id, 'scopes' => '{}']]);

    $positions = getJson(route('grp.api.profile'))->assertOk()->json('data.job_positions');

    expect($positions)->toContain(['name' => $jobPosition->name, 'code' => $jobPosition->code]);
});

test('get api profile unauthenticated', function () {
    $this->app['auth']->forgetGuards();

    $response = getJson(route('grp.api.profile'));
    $response->assertUnauthorized();
});

test('show api group', function () {
    $response = getJson(route('grp.api.group.show'));
    $response->assertOk();
    $response->assertJsonStructure([
        'data' => ['id', 'slug', 'code', 'name'],
    ]);
});

test('index api organisations', function () {
    $response = getJson(route('grp.api.organisations.index'));
    $response->assertOk();
    $response->assertJsonStructure([
        'data' => [['id', 'slug', 'name', 'type', 'code']],
    ]);
});

test('show api organisation', function () {
    $response = getJson(route('grp.api.organisations.show', $this->organisation));
    $response->assertOk();
    $response->assertJsonStructure([
        'data' => ['id', 'slug', 'code', 'name', 'type'],
    ]);
});

test('index api shops in organisation', function () {
    $response = getJson(route('grp.api.organisations.show.shops.index', $this->organisation));
    $response->assertOk();
    $response->assertJsonStructure([
        'data' => [['id', 'slug', 'code', 'name', 'type', 'state']],
    ]);
});

test('index api shops', function () {
    $response = getJson(route('grp.api.shops.index'));
    $response->assertOk();
    $response->assertJsonStructure([
        'data' => [['id', 'slug', 'code', 'name', 'type', 'state']],
    ]);
});

test('show api shop', function () {
    $response = getJson(route('grp.api.shops.show.', $this->shop));
    $response->assertOk();
    $response->assertJsonStructure([
        'data' => ['id', 'slug', 'code', 'name', 'state'],
    ]);
});

test('index api orders', function () {
    $order = StoreOrder::make()->action($this->customer, Order::factory()->definition());
    SubmitOrder::make()->action($order);

    $response = getJson(route('grp.api.shops.show.orders.index', $this->shop));
    $response->assertOk();
    $response->assertJsonStructure([
        'data' => [['id', 'reference', 'state', 'net_amount', 'total_amount', 'date']],
    ]);
});

test('show api order', function () {
    $order = StoreOrder::make()->action($this->customer, Order::factory()->definition());
    SubmitOrder::make()->action($order);

    $response = getJson(route('grp.api.shops.show.orders.show', [$this->shop, $order]));
    $response->assertOk();
    $response->assertJsonStructure([
        'data' => ['id', 'reference', 'state', 'net_amount', 'total_amount', 'date', 'customer'],
    ]);
});

test('index api customers', function () {
    $response = getJson(route('grp.api.shops.show.customers.index', $this->shop));
    $response->assertOk();
    $response->assertJsonStructure([
        'data' => [['id', 'slug', 'reference', 'name', 'email']],
    ]);
});

test('show api customer', function () {
    $response = getJson(route('grp.api.shops.show.customers.show', [$this->shop, $this->customer]));
    $response->assertOk();
    $response->assertJsonStructure([
        'data' => ['slug', 'reference', 'name', 'email'],
    ]);
});

test('index api invoices in customer', function () {
    StoreInvoice::make()->action($this->customer, Invoice::factory()->definition());

    $response = getJson(route('grp.api.shops.show.customers.invoices', [$this->shop, $this->customer]));
    $response->assertOk();
    $response->assertJsonStructure([
        'data' => [['id', 'slug', 'reference', 'total_amount', 'net_amount']],
    ]);
});

test('index api orders in customer', function () {
    $order = StoreOrder::make()->action($this->customer, Order::factory()->definition());
    SubmitOrder::make()->action($order);

    $response = getJson(route('grp.api.shops.show.customers.orders', [$this->shop, $this->customer]));
    $response->assertOk();
    $response->assertJsonStructure([
        'data' => [['id', 'reference', 'state', 'net_amount', 'total_amount']],
    ]);
});

test('index api invoices', function () {
    StoreInvoice::make()->action($this->customer, Invoice::factory()->definition());

    $response = getJson(route('grp.api.shops.show.invoices.index', $this->shop));
    $response->assertOk();
    $response->assertJsonStructure([
        'data' => [['id', 'slug', 'reference', 'total_amount', 'net_amount']],
    ]);
});

test('show api invoice', function () {
    $invoice = StoreInvoice::make()->action($this->customer, Invoice::factory()->definition());

    $response = getJson(route('grp.api.shops.show.invoices.show', [$this->shop, $invoice]));
    $response->assertOk();
    $response->assertJsonStructure([
        'slug', 'reference', 'total_amount', 'net_amount', 'currency_code',
    ]);
});

test('the pace mod posts claude.ai weekly usage readings, kept only when newer and changed', function () {
    $account = 'Claude test '.uniqid();
    $post    = fn (array $reading) => postJson(route('grp.api.chat_ai.account_usage'), [
        'account'    => $account,
        'resets_at'  => '2026-11-04T23:00:00Z',
        'sampled_at' => '2026-11-01T02:19:00Z',
        ...$reading,
    ]);

    $this->user->update(['can_use_mcp_customer_service' => false]);
    $post(['percent_used' => 55])->assertForbidden();

    $this->user->update(['can_use_mcp_customer_service' => true]);
    $post(['percent_used' => 101])->assertUnprocessable()->assertJsonValidationErrors('percent_used');
    $post(['percent_used' => 55])->assertOk()->assertJson(['stored' => true]);
    $post(['percent_used' => 55, 'sampled_at' => '2026-11-01T02:24:00Z'])->assertJson(['stored' => false]);
    $post(['percent_used' => 54, 'sampled_at' => '2026-11-01T02:10:00Z'])->assertJson(['stored' => false]);
    $post(['percent_used' => 56, 'sampled_at' => '2026-11-01T02:29:00Z'])->assertJson(['stored' => true]);

    expect(ChatAiAccountUsageSample::where('account', $account)->orderBy('sampled_at')->get()->map(fn ($sample) => [$sample->percent_used, $sample->sampled_at->toIso8601String()])->all())
        ->toBe([['55.00', '2026-11-01T02:24:00+00:00'], ['56.00', '2026-11-01T02:29:00+00:00']]);
});

test('mobile ping is not found without an app key', function () {
    config(['app.mobile_app_keys' => ['first-mobile-key', 'second-mobile-key']]);

    getJson(route('grp.api.mobile.ping'))->assertNotFound();
});

test('mobile ping is not found with an unknown app key', function () {
    config(['app.mobile_app_keys' => ['first-mobile-key', 'second-mobile-key']]);

    getJson(route('grp.api.mobile.ping'), ['X-Aiku-App-Key' => 'unknown-mobile-key'])->assertNotFound();
});

test('mobile ping answers with either configured app key', function () {
    config(['app.mobile_app_keys' => ['first-mobile-key', 'second-mobile-key']]);

    getJson(route('grp.api.mobile.ping'), ['X-Aiku-App-Key' => 'first-mobile-key'])->assertOk()->assertContent('pong');
    getJson(route('grp.api.mobile.ping'), ['X-Aiku-App-Key' => 'second-mobile-key'])->assertOk()->assertContent('pong');
});

test('mobile ping is not found when no app keys are configured', function () {
    config(['app.mobile_app_keys' => []]);

    getJson(route('grp.api.mobile.ping'), ['X-Aiku-App-Key' => 'first-mobile-key'])->assertNotFound();
});

test('grp api ping outside mobile answers without an app key', function () {
    config(['app.mobile_app_keys' => ['first-mobile-key']]);

    getJson(route('grp.api.ping'))->assertOk()->assertContent('pong');
});

test('mobile login is not found without the app key', function () {
    $user = createMobileAppUser($this->group);

    postMobileLogin([
        'username'    => $user->username,
        'password'    => 'mobile-app-password',
        'device_name' => 'Pixel 8 · Android 15',
    ], [])->assertNotFound();
});

test('mobile login with correct credentials returns a mobile token that lasts until sign out', function () {
    $user = createMobileAppUser($this->group);

    $response = postMobileLogin([
        'username'    => $user->username,
        'password'    => 'mobile-app-password',
        'device_name' => 'Pixel 8 · Android 15',
    ]);

    $response->assertOk()->assertJsonStructure([
        'token',
        'expires_at',
        'user' => ['id', 'username', 'contact_name', 'email', 'status', 'app_theme', 'employee', 'guest'],
    ]);
    expect($response->json('user.id'))->toBe($user->id)
        ->and($response->json('expires_at'))->toBeNull();

    $accessToken = PersonalAccessToken::findToken($response->json('token'));
    expect($accessToken->name)->toBe('mobile|Pixel 8 · Android 15')
        ->and($accessToken->abilities)->toBe(['mobile'])
        ->and($accessToken->expires_at)->toBeNull();
});

test('mobile login with a wrong password is refused and locks on the sixth attempt', function () {
    $user = createMobileAppUser($this->group);
    $body = [
        'username'    => $user->username,
        'password'    => 'wrong-password',
        'device_name' => 'Pixel 8 · Android 15',
    ];

    foreach (range(1, 5) as $_) {
        postMobileLogin($body)->assertUnprocessable()->assertJsonValidationErrors(['username' => trans('auth.failed')]);
    }

    postMobileLogin($body)->assertUnprocessable()->assertJsonValidationErrors(['username' => Str::before(trans('auth.throttle'), ':seconds')]);
});

test('mobile login refuses an inactive user', function () {
    $user = createMobileAppUser($this->group, ['status' => false]);

    postMobileLogin([
        'username'    => $user->username,
        'password'    => 'mobile-app-password',
        'device_name' => 'Pixel 8 · Android 15',
    ])->assertUnprocessable()->assertJsonValidationErrors(['username' => trans('auth.failed')]);
});

test('mobile login twice from the same device leaves one token for it', function () {
    $user = createMobileAppUser($this->group);
    $body = [
        'username'    => $user->username,
        'password'    => 'mobile-app-password',
        'device_name' => 'Pixel 8 · Android 15',
    ];

    postMobileLogin($body)->assertOk();
    postMobileLogin($body)->assertOk();

    expect($user->tokens()->where('name', 'mobile|Pixel 8 · Android 15')->count())->toBe(1);
});

test('mobile login asks for the one time password and accepts a valid one', function () {
    $google2fa = new Google2FA();
    $secret    = $google2fa->generateSecretKey();
    $user      = createMobileAppUser($this->group, ['google2fa_secret' => $secret]);
    $body      = [
        'username'    => $user->username,
        'password'    => 'mobile-app-password',
        'device_name' => 'Pixel 8 · Android 15',
    ];

    postMobileLogin($body)->assertUnprocessable()
        ->assertJsonPath('code', 'otp_required')
        ->assertJsonValidationErrors(['one_time_password' => 'Enter OTP from your Authenticator App']);

    postMobileLogin([...$body, 'one_time_password' => $google2fa->getCurrentOtp($secret)])->assertOk()->assertJsonStructure(['token', 'expires_at']);
});

test('mobile login locks the one time password after five wrong codes', function () {
    $user = createMobileAppUser($this->group, ['google2fa_secret' => (new Google2FA())->generateSecretKey()]);
    $body = [
        'username'          => $user->username,
        'password'          => 'mobile-app-password',
        'device_name'       => 'Pixel 8 · Android 15',
        'one_time_password' => '12345',
    ];

    foreach (range(1, 5) as $_) {
        postMobileLogin($body)->assertUnprocessable()->assertJsonValidationErrors(['one_time_password' => 'Invalid OTP is given']);
    }

    postMobileLogin($body)->assertUnprocessable()->assertJsonValidationErrors(['one_time_password' => 'Too many wrong codes']);
});

test('mobile login asks a user who must use two factor but has not set it up to set it up in the app', function () {
    $user = createMobileAppUser($this->group, ['is_two_factor_required' => true]);

    postMobileLogin([
        'username'    => $user->username,
        'password'    => 'mobile-app-password',
        'device_name' => 'Pixel 8 · Android 15',
    ])->assertUnprocessable()
        ->assertJsonPath('code', 'two_factor_setup_required')
        ->assertJsonValidationErrors(['one_time_password' => 'Set up two-factor authentication to continue.']);
});

test('mobile logout deletes the token it was called with', function () {
    $user  = createMobileAppUser($this->group);
    $token = postMobileLogin([
        'username'    => $user->username,
        'password'    => 'mobile-app-password',
        'device_name' => 'Pixel 8 · Android 15',
    ])->json('token');
    $this->app['auth']->forgetGuards();

    postJson(route('grp.api.mobile.logout'), [], [
        'X-Aiku-App-Key' => 'mobile-test-key',
        'Authorization'  => 'Bearer '.$token,
    ])->assertNoContent();

    expect(PersonalAccessToken::findToken($token))->toBeNull();
});

test('mobile logout without a token is unauthorised', function () {
    config(['app.mobile_app_keys' => ['mobile-test-key']]);
    $this->app['auth']->forgetGuards();

    postJson(route('grp.api.mobile.logout'), [], ['X-Aiku-App-Key' => 'mobile-test-key'])->assertUnauthorized();
});

test('mobile permissions list the signed in user permissions and roles', function () {
    $user = createMobileAppUser($this->group);
    setPermissionsTeamId($this->group->id);
    $permission = \Spatie\Permission\Models\Permission::query()->where('guard_name', 'web')->orderBy('name')->value('name');
    $user->givePermissionTo($permission);
    $token = $user->createToken('mobile|iPhone', ['mobile'])->plainTextToken;
    config(['app.mobile_app_keys' => ['mobile-test-key']]);
    $this->app['auth']->forgetGuards();
    setPermissionsTeamId(null);

    $response = getJson(route('grp.api.mobile.permissions'), [
        'X-Aiku-App-Key' => 'mobile-test-key',
        'Authorization'  => 'Bearer '.$token,
    ])->assertOk();

    expect($response->json('user_id'))->toBe($user->id)
        ->and($response->json('permissions'))->toContain($permission)
        ->and($response->json('roles'))->toBeArray();
});

test('mobile permissions need a mobile token', function () {
    $user = createMobileAppUser($this->group);
    config(['app.mobile_app_keys' => ['mobile-test-key']]);
    $this->app['auth']->forgetGuards();

    getJson(route('grp.api.mobile.permissions'), ['X-Aiku-App-Key' => 'mobile-test-key'])->assertUnauthorized();
    $this->app['auth']->forgetGuards();
    getJson(route('grp.api.mobile.permissions'), [
        'X-Aiku-App-Key' => 'mobile-test-key',
        'Authorization'  => 'Bearer '.$user->createToken('other', ['read'])->plainTextToken,
    ])->assertForbidden();
});

test('an expired mobile token is unauthorised on the api profile', function () {
    $user  = createMobileAppUser($this->group);
    $token = $user->createToken('mobile|Old phone', ['mobile'], now()->subMinute())->plainTextToken;
    $this->app['auth']->forgetGuards();

    getJson(route('grp.api.profile'), ['Authorization' => 'Bearer '.$token])->assertUnauthorized();
});

test('mobile password reset link is not found without the app key', function () {
    config(['app.mobile_app_keys' => ['mobile-test-key']]);

    postJson(route('grp.api.mobile.password.reset_link'), ['email' => 'someone@example.com'])->assertNotFound();
});

test('mobile password reset link emails the staff reset link and answers an unknown email the same way', function () {
    Notification::fake();
    config(['app.mobile_app_keys' => ['mobile-test-key']]);
    $user    = createMobileAppUser($this->group, ['email' => 'mobile-forgot-'.uniqid().'@example.com']);
    $headers = ['X-Aiku-App-Key' => 'mobile-test-key'];

    postJson(route('grp.api.mobile.password.reset_link'), ['email' => 'nobody-'.uniqid().'@example.com'], $headers)->assertOk();
    Notification::assertNothingSent();

    postJson(route('grp.api.mobile.password.reset_link'), ['email' => $user->email], $headers)->assertOk();
    Notification::assertSentTo($user, ResetPassword::class, function (ResetPassword $notification) use ($user) {
        return strtok($notification->toMail($user)->actionUrl, '?') === route('grp.email.reset-password.show');
    });
});

test('mobile password reset link needs a valid email', function () {
    config(['app.mobile_app_keys' => ['mobile-test-key']]);

    postJson(route('grp.api.mobile.password.reset_link'), ['email' => 'not-an-email'], ['X-Aiku-App-Key' => 'mobile-test-key'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('email');
});

test('api profile includes the user app theme', function () {
    $appTheme = ['#4f46e5', '#f3f4f6', '#362cdb', '#fde047', '#4f46e5', '#f3f4f6', '#fcd34d', '#374151'];
    $user     = createMobileAppUser($this->group, ['settings' => ['app_theme' => $appTheme]]);
    Sanctum::actingAs($user);

    getJson(route('grp.api.profile'))->assertOk()->assertJsonPath('data.app_theme', $appTheme);
});

test('mobile warehouses list what a dispatch clerk may do in each warehouse', function () {
    config(['app.mobile_app_keys' => ['mobile-test-key']]);
    Auth::shouldUse('web');
    $warehouse = createWarehouse();
    $user      = createMobileAppUser($this->group);
    $user->assignRole(RolesEnum::getRoleName(RolesEnum::DISPATCH_CLERK->value, $warehouse));
    SetUserAuthorisedModels::run($user);
    Sanctum::actingAs($user, ['mobile']);

    getJson(route('grp.api.mobile.warehouses'), ['X-Aiku-App-Key' => 'mobile-test-key'])
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $warehouse->id)
        ->assertJsonPath('data.0.code', $warehouse->code)
        ->assertJsonPath('data.0.organisation.id', $warehouse->organisation_id)
        ->assertJsonPath('data.0.organisation.code', $warehouse->organisation->code)
        ->assertJsonPath('data.0.can.view_goods_out', true)
        ->assertJsonPath('data.0.can.pick_and_pack', true)
        ->assertJsonPath('data.0.can.supervise_goods_out', false)
        ->assertJsonPath('data.0.can.view_locations', true)
        ->assertJsonPath('data.0.can.edit_locations', false)
        ->assertJsonPath('data.0.can.view_inventory', false)
        ->assertJsonPath('data.0.can.move_stock', false)
        ->assertJsonPath('data.0.can.view_goods_in', false)
        ->assertJsonPath('data.0.can.book_in', false)
        ->assertJsonPath('data.0.can.view_fulfilment', false)
        ->assertJsonPath('data.0.can.edit_fulfilment', false);
});

test('mobile warehouses let a fulfilment warehouse worker handle fulfilment but not pick', function () {
    config(['app.mobile_app_keys' => ['mobile-test-key']]);
    Auth::shouldUse('web');
    $warehouse = createWarehouse();
    $user      = createMobileAppUser($this->group);
    $user->assignRole(RolesEnum::getRoleName(RolesEnum::FULFILMENT_WAREHOUSE_WORKER->value, $warehouse));
    SetUserAuthorisedModels::run($user);
    Sanctum::actingAs($user, ['mobile']);

    getJson(route('grp.api.mobile.warehouses'), ['X-Aiku-App-Key' => 'mobile-test-key'])
        ->assertOk()
        ->assertJsonPath('data.0.can.view_fulfilment', true)
        ->assertJsonPath('data.0.can.edit_fulfilment', true)
        ->assertJsonPath('data.0.can.pick_and_pack', false);
});

test('mobile warehouses let a goods in clerk book in but not pick', function () {
    config(['app.mobile_app_keys' => ['mobile-test-key']]);
    Auth::shouldUse('web');
    $warehouse = createWarehouse();
    $user      = createMobileAppUser($this->group);
    $user->assignRole(RolesEnum::getRoleName(RolesEnum::GOODS_IN_CLERK->value, $warehouse));
    SetUserAuthorisedModels::run($user);
    Sanctum::actingAs($user, ['mobile']);

    getJson(route('grp.api.mobile.warehouses'), ['X-Aiku-App-Key' => 'mobile-test-key'])
        ->assertOk()
        ->assertJsonPath('data.0.can.view_goods_in', true)
        ->assertJsonPath('data.0.can.book_in', true)
        ->assertJsonPath('data.0.can.supervise_goods_in', false)
        ->assertJsonPath('data.0.can.edit_procurement', false)
        ->assertJsonPath('data.0.can.place_stock_deliveries', false)
        ->assertJsonPath('data.0.can.view_goods_out', false)
        ->assertJsonPath('data.0.can.pick_and_pack', false);
});

test('mobile warehouses let a goods in supervisor unmark as received but not place or cancel', function () {
    config(['app.mobile_app_keys' => ['mobile-test-key']]);
    Auth::shouldUse('web');
    $warehouse = createWarehouse();
    $user      = createMobileAppUser($this->group);
    $user->assignRole(RolesEnum::getRoleName(RolesEnum::GOODS_IN_SUPERVISOR->value, $warehouse));
    SetUserAuthorisedModels::run($user);
    Sanctum::actingAs($user, ['mobile']);

    getJson(route('grp.api.mobile.warehouses'), ['X-Aiku-App-Key' => 'mobile-test-key'])
        ->assertOk()
        ->assertJsonPath('data.0.can.book_in', true)
        ->assertJsonPath('data.0.can.supervise_goods_in', true)
        ->assertJsonPath('data.0.can.edit_procurement', false)
        ->assertJsonPath('data.0.can.place_stock_deliveries', false);
});

test('mobile warehouses are empty for a user with no warehouse role', function () {
    config(['app.mobile_app_keys' => ['mobile-test-key']]);
    Sanctum::actingAs(createMobileAppUser($this->group), ['mobile']);

    getJson(route('grp.api.mobile.warehouses'), ['X-Aiku-App-Key' => 'mobile-test-key'])
        ->assertOk()
        ->assertExactJson(['data' => []]);
});

test('mobile warehouses need a mobile token and the app key', function () {
    config(['app.mobile_app_keys' => ['mobile-test-key']]);
    $user = createMobileAppUser($this->group);

    getJson(route('grp.api.mobile.warehouses'))->assertNotFound();

    Sanctum::actingAs($user, ['other']);
    getJson(route('grp.api.mobile.warehouses'), ['X-Aiku-App-Key' => 'mobile-test-key'])->assertForbidden();
});

/**
 * @return array{0: Employee, 1: User}
 */
function createClockingEmployeeUser(Organisation $organisation): array
{
    $employee = Employee::factory()->create([
        'organisation_id' => $organisation->id,
        'group_id'        => $organisation->group_id,
        'state'           => EmployeeStateEnum::WORKING,
        'email'           => 'mobile-clock-'.uniqid().'@example.com',
        'worker_number'   => 'MC'.uniqid(),
    ]);
    $employee->update(['pin' => $organisation->id.':MB'.$employee->id]);

    $user = StoreUserFromEmployee::make()->handle($employee, [
        'username' => 'mobile-clock-'.$employee->id,
        'password' => 'secret123',
    ]);

    return [$employee->fresh(), $user];
}

function createMobileClockingQrCode(Organisation $organisation): ClockingMachineQRCode
{
    $workplace = StoreWorkplace::make()->action($organisation, [
        'name' => 'Mobile clocking workplace '.uniqid(),
        'type' => WorkplaceTypeEnum::HQ,
    ]);

    $clockingMachine = StoreClockingMachine::make()->action($workplace, [
        'name' => 'Mobile clocking QR machine '.uniqid(),
        'type' => ClockingMachineTypeEnum::QR_CODE->value,
    ]);
    $clockingMachine->update(['config' => ['qr' => ['enable' => true]]]);

    return StoreClockingMachineQRCode::make()->handle($clockingMachine, ['label' => 'Entrance']);
}

test('mobile clocking shows a clocked out employee with the methods of their organisation and their code', function () {
    config(['app.mobile_app_keys' => ['mobile-test-key']]);
    [$employee, $user] = createClockingEmployeeUser($this->organisation);
    createMobileClockingQrCode($this->organisation);
    Sanctum::actingAs($user, ['mobile']);

    getJson(route('grp.api.mobile.clocking.show'), ['X-Aiku-App-Key' => 'mobile-test-key'])
        ->assertOk()
        ->assertJsonPath('data.employee.id', $employee->id)
        ->assertJsonPath('data.employee.organisation.code', $this->organisation->code)
        ->assertJsonPath('data.status', 'clocked_out')
        ->assertJsonPath('data.since', null)
        ->assertJsonPath('data.code', substr($employee->pin, strlen((string) $this->organisation->id) + 1))
        ->assertJsonPath('data.sessions', [])
        ->assertJsonPath('data.can_edit_notes', false)
        ->assertJson(['data' => ['methods' => ['qr_code']]]);
});

test('scanning a clocking machine QR code from the app clocks the employee in and shows it in the status and timesheets', function () {
    config(['app.mobile_app_keys' => ['mobile-test-key']]);
    [, $user] = createClockingEmployeeUser($this->organisation);
    $qrCode   = createMobileClockingQrCode($this->organisation);
    $headers  = ['X-Aiku-App-Key' => 'mobile-test-key'];
    Sanctum::actingAs($user, ['mobile']);

    $clockingId = postJson(route('grp.api.mobile.clocking.qr'), ['qr_code' => $qrCode->hash], $headers)
        ->assertOk()
        ->assertJsonPath('success', true)
        ->json('clocking.id');

    getJson(route('grp.api.mobile.clocking.show'), $headers)
        ->assertOk()
        ->assertJsonPath('data.status', 'clocked_in')
        ->assertJsonCount(1, 'data.sessions')
        ->assertJsonPath('data.sessions.0.clock_in.id', $clockingId);

    expect(getJson(route('grp.api.mobile.clocking.show'), $headers)->json('data.since'))->not->toBeNull();

    getJson(route('grp.api.mobile.clocking.timesheets'), $headers)
        ->assertOk()
        ->assertJsonCount(1, 'data.timesheets')
        ->assertJsonPath('data.timesheets.0.sessions.0.clock_in.id', $clockingId);

    patchJson(route('grp.api.mobile.clocking.notes', $clockingId), ['notes' => 'Came in through the side gate'], $headers)->assertForbidden();
});

test('only staff who may edit clockings on the web can note their own clocking from the app', function () {
    config(['app.mobile_app_keys' => ['mobile-test-key']]);
    [, $user] = createClockingEmployeeUser($this->organisation);
    $qrCode   = createMobileClockingQrCode($this->organisation);
    $headers  = ['X-Aiku-App-Key' => 'mobile-test-key'];
    actingAsUserWithOnlyPermissions($user, ["human-resources.{$this->organisation->id}.edit"]);
    Sanctum::actingAs($user->refresh(), ['mobile']);

    $clockingId = postJson(route('grp.api.mobile.clocking.qr'), ['qr_code' => $qrCode->hash], $headers)->json('clocking.id');

    getJson(route('grp.api.mobile.clocking.show'), $headers)->assertJsonPath('data.can_edit_notes', true);

    patchJson(route('grp.api.mobile.clocking.notes', $clockingId), ['notes' => 'Came in through the side gate'], $headers)
        ->assertOk()
        ->assertJsonPath('data.notes', 'Came in through the side gate');
});

test('the app timesheets give the target of the week from the employee\'s own work schedule', function () {
    config(['app.mobile_app_keys' => ['mobile-test-key']]);
    [$employee, $user] = createClockingEmployeeUser($this->organisation);
    $workingDay        = ['s' => '08:00', 'e' => '16:00', 'ub' => 30];
    StoreWorkSchedule::make()->action($employee, [
        'name'          => 'Four day week',
        'type'          => 'default',
        'working_hours' => ['data' => [1 => $workingDay, 2 => $workingDay, 3 => $workingDay, 4 => $workingDay]],
    ]);
    $monday = Carbon::create(2040, 1, 2)->startOfWeek();
    Sanctum::actingAs($user, ['mobile']);

    getJson(route('grp.api.mobile.clocking.timesheets', ['from' => $monday->toDateString(), 'to' => $monday->copy()->addDays(6)->toDateString()]), ['X-Aiku-App-Key' => 'mobile-test-key'])
        ->assertOk()
        ->assertJsonPath('data.target_seconds', 4 * (8 * 60 - 30) * 60);
});

test('the app gets the reason when a clocking QR code is refused', function () {
    config(['app.mobile_app_keys' => ['mobile-test-key']]);
    [, $user] = createClockingEmployeeUser($this->organisation);
    Sanctum::actingAs($user, ['mobile']);

    postJson(route('grp.api.mobile.clocking.qr'), ['qr_code' => 'not-a-clocking-qr'], ['X-Aiku-App-Key' => 'mobile-test-key'])
        ->assertStatus(400)
        ->assertJsonPath('success', false)
        ->assertJsonPath('message', __('Invalid QR Code.'));
});

test('staff can not change the notes of someone else\'s clocking from the app', function () {
    config(['app.mobile_app_keys' => ['mobile-test-key']]);
    [, $owner] = createClockingEmployeeUser($this->organisation);
    [, $other] = createClockingEmployeeUser($this->organisation);
    $qrCode    = createMobileClockingQrCode($this->organisation);
    $headers   = ['X-Aiku-App-Key' => 'mobile-test-key'];

    Sanctum::actingAs($owner, ['mobile']);
    $clockingId = postJson(route('grp.api.mobile.clocking.qr'), ['qr_code' => $qrCode->hash], $headers)->json('clocking.id');

    Sanctum::actingAs($other, ['mobile']);
    patchJson(route('grp.api.mobile.clocking.notes', $clockingId), ['notes' => 'Not mine'], $headers)->assertForbidden();
});

test('a user without an employee record is clocked out in the app with no clocking methods', function () {
    config(['app.mobile_app_keys' => ['mobile-test-key']]);
    Sanctum::actingAs(createMobileAppUser($this->group), ['mobile']);

    getJson(route('grp.api.mobile.clocking.show'), ['X-Aiku-App-Key' => 'mobile-test-key'])
        ->assertOk()
        ->assertJsonPath('data.employee', null)
        ->assertJsonPath('data.status', 'clocked_out')
        ->assertJsonPath('data.methods', []);
});

/**
 * @return array{0: Employee, 1: User}
 */
function createMobileLeaveEmployeeUser(Organisation $organisation): array
{
    [$employee, $user] = createClockingEmployeeUser($organisation);
    StoreEmployeeContract::make()->action($employee, ['start_date' => now()->subMonth()->toDateString(), 'annual_leave_days' => 20]);

    return [$employee, $user];
}

function createMobileMedicalLeaveType(Organisation $organisation): LeaveType
{
    return StoreLeaveType::make()->action($organisation, [
        'code'     => 'MB'.rand(10000, 99999),
        'name'     => 'Mobile medical leave '.uniqid(),
        'category' => LeaveCategoryEnum::MEDICAL,
    ]);
}

function nextFreeMobileLeaveDay(Organisation $organisation): Carbon
{
    $day = now()->addDays(10)->startOfDay();

    while ($day->isWeekend()
        || Holiday::where('organisation_id', $organisation->id)->whereDate('from', '<=', $day)->whereDate('to', '>=', $day)->exists()
        || RestrictedPeriod::where('organisation_id', $organisation->id)->where('is_active', true)->whereDate('start_date', '<=', $day)->whereDate('end_date', '>=', $day)->exists()) {
        $day->addDay();
    }

    return $day;
}

function requestMobileLeave(LeaveType $leaveType, Carbon $day, array $overrides = []): TestResponse
{
    return post(route('grp.api.mobile.clocking.leaves.store'), array_merge([
        'type'       => $leaveType->code,
        'start_date' => $day->toDateString(),
        'end_date'   => $day->toDateString(),
        'reason'     => 'Dentist appointment',
    ], $overrides), ['X-Aiku-App-Key' => 'mobile-test-key']);
}

function createMobileOvertimeType(Organisation $organisation): OvertimeType
{
    return StoreOvertimeType::make()->action($organisation, [
        'code'              => 'MO'.rand(10000, 99999),
        'name'              => 'Mobile overtime '.uniqid(),
        'category'          => OvertimeCategoryEnum::OVERTIME,
        'compensation_type' => OvertimeCompensationTypeEnum::PAID,
    ]);
}

function mobileOvertimeRequestBody(OvertimeType $overtimeType, int $durationMinutes): array
{
    $startsAt = now()->subDay()->setTime(18, 0);

    return [
        'overtime_type_id'           => $overtimeType->id,
        'requested_date'             => $startsAt->toDateString(),
        'requested_start_at'         => $startsAt->toIso8601String(),
        'requested_end_at'           => $startsAt->copy()->addMinutes($durationMinutes)->toIso8601String(),
        'requested_duration_minutes' => $durationMinutes,
        'reason'                     => 'Stock take',
    ];
}

test('mobile timesheets carry the statistics, where each clocking was made and a pending correction of the day', function () {
    config(['app.mobile_app_keys' => ['mobile-test-key']]);
    [$employee, $user] = createClockingEmployeeUser($this->organisation);
    $qrCode            = createMobileClockingQrCode($this->organisation);
    $headers           = ['X-Aiku-App-Key' => 'mobile-test-key'];
    Sanctum::actingAs($user, ['mobile']);

    postJson(route('grp.api.mobile.clocking.qr'), ['qr_code' => $qrCode->hash], $headers)->assertOk();
    $timesheet = Timesheet::where('subject_type', 'Employee')->where('subject_id', $employee->id)->firstOrFail();

    getJson(route('grp.api.mobile.clocking.timesheets'), $headers)
        ->assertOk()
        ->assertJsonPath('data.statistics.total', 1)
        ->assertJsonPath('data.statistics.no_clock_out', 1)
        ->assertJsonPath('data.timesheets.0.id', $timesheet->id)
        ->assertJsonPath('data.timesheets.0.clock_in_count', 1)
        ->assertJsonPath('data.timesheets.0.number_open_time_trackers', 1)
        ->assertJsonPath('data.timesheets.0.has_pending_adjustment', false)
        ->assertJsonPath('data.timesheets.0.sessions.0.clock_in.clocking_machine.id', $qrCode->clocking_machine_id)
        ->assertJsonPath('data.timesheets.0.sessions.0.clock_in.workplace.id', $qrCode->clockingMachine->workplace_id);

    post(route('grp.api.mobile.clocking.adjustments.store'), [
        'timesheet_id'     => $timesheet->id,
        'date'             => $timesheet->date->toDateString(),
        'requested_end_at' => '23:59',
        'reason'           => 'Forgot to clock out',
    ], $headers)->assertCreated()->assertJsonPath('data.timesheet_id', $timesheet->id);

    getJson(route('grp.api.mobile.clocking.timesheets'), $headers)->assertJsonPath('data.timesheets.0.has_pending_adjustment', true);

    getJson(route('grp.api.mobile.clocking.timesheets', ['from' => '2026-11-10', 'to' => '2026-11-01']), $headers)
        ->assertUnprocessable()
        ->assertJsonValidationErrors('to');
});

test('staff can not ask from the app to correct someone else\'s timesheet', function () {
    config(['app.mobile_app_keys' => ['mobile-test-key']]);
    [$owner, $ownerUser] = createClockingEmployeeUser($this->organisation);
    [, $other]           = createClockingEmployeeUser($this->organisation);
    $qrCode              = createMobileClockingQrCode($this->organisation);
    $headers             = ['X-Aiku-App-Key' => 'mobile-test-key'];

    Sanctum::actingAs($ownerUser, ['mobile']);
    postJson(route('grp.api.mobile.clocking.qr'), ['qr_code' => $qrCode->hash], $headers)->assertOk();
    $timesheet = Timesheet::where('subject_type', 'Employee')->where('subject_id', $owner->id)->firstOrFail();

    Sanctum::actingAs($other, ['mobile']);
    post(route('grp.api.mobile.clocking.adjustments.store'), [
        'timesheet_id'     => $timesheet->id,
        'date'             => $timesheet->date->toDateString(),
        'requested_end_at' => '23:59',
        'reason'           => 'Not my day',
    ], $headers)->assertUnprocessable()->assertJsonValidationErrors('date');

    expect(AttendanceAdjustment::where('timesheet_id', $timesheet->id)->exists())->toBeFalse();
});

test('staff request a leave with a medical certificate from the app and see it with their balance and leave types', function () {
    config(['app.mobile_app_keys' => ['mobile-test-key']]);
    Storage::fake('media');
    [$employee, $user] = createMobileLeaveEmployeeUser($this->organisation);
    $leaveType         = createMobileMedicalLeaveType($this->organisation);
    $day               = nextFreeMobileLeaveDay($this->organisation);
    $headers           = ['X-Aiku-App-Key' => 'mobile-test-key'];
    Sanctum::actingAs($user, ['mobile']);

    $leave = requestMobileLeave($leaveType, $day, ['attachments' => [UploadedFile::fake()->image('certificate.jpg')]])
        ->assertCreated()
        ->assertJsonPath('data.employee_id', $employee->id)
        ->assertJsonPath('data.type', $leaveType->code)
        ->assertJsonPath('data.type_label', $leaveType->name)
        ->assertJsonPath('data.start_date', $day->toDateString())
        ->assertJsonPath('data.status', 'pending')
        ->assertJsonPath('data.attachments.0.name', 'certificate.jpg')
        ->json('data');

    $leavesTab = getJson(route('grp.api.mobile.clocking.leaves.index'), $headers)
        ->assertOk()
        ->assertJsonCount(1, 'data.leaves')
        ->assertJsonPath('data.leaves.0.id', $leave['id'])
        ->assertJsonPath('data.pagination.total', 1)
        ->assertJsonPath('data.balance.annual_days', 20)
        ->assertJsonPath('data.annual_remaining_after_submission', 20)
        ->json('data');

    expect(collect($leavesTab['type_options'])->firstWhere('code', $leaveType->code))
        ->toMatchArray(['label' => $leaveType->name, 'category' => 'medical']);

    get($leave['attachments'][0]['url'], $headers)->assertOk();
});

test('a leave request from the app needs a reason', function () {
    config(['app.mobile_app_keys' => ['mobile-test-key']]);
    [$employee, $user] = createMobileLeaveEmployeeUser($this->organisation);
    Sanctum::actingAs($user, ['mobile']);

    requestMobileLeave(createMobileMedicalLeaveType($this->organisation), nextFreeMobileLeaveDay($this->organisation), ['reason' => ''])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('reason');

    expect(Leave::where('employee_id', $employee->id)->exists())->toBeFalse();
});

test('staff replace the medical certificate of their own pending leave from the app but not someone else\'s', function () {
    config(['app.mobile_app_keys' => ['mobile-test-key']]);
    Storage::fake('media');
    [, $owner] = createMobileLeaveEmployeeUser($this->organisation);
    [, $other] = createMobileLeaveEmployeeUser($this->organisation);
    $headers   = ['X-Aiku-App-Key' => 'mobile-test-key'];

    Sanctum::actingAs($owner, ['mobile']);
    $leave = requestMobileLeave(createMobileMedicalLeaveType($this->organisation), nextFreeMobileLeaveDay($this->organisation), ['attachments' => [UploadedFile::fake()->image('first.jpg')]])
        ->assertCreated()
        ->json('data');

    Sanctum::actingAs($other, ['mobile']);
    post(route('grp.api.mobile.clocking.leaves.update', $leave['id']), ['attachments' => [UploadedFile::fake()->image('not-mine.jpg')]], $headers)->assertForbidden();
    get($leave['attachments'][0]['url'], $headers)->assertForbidden();

    Sanctum::actingAs($owner, ['mobile']);
    post(route('grp.api.mobile.clocking.leaves.update', $leave['id']), ['attachments' => [UploadedFile::fake()->image('second.jpg')]], $headers)
        ->assertOk()
        ->assertJsonCount(1, 'data.attachments')
        ->assertJsonPath('data.attachments.0.name', 'second.jpg');
});

test('staff request a clocking adjustment for a day with no clockings, see it listed and cancel it from the app', function () {
    config(['app.mobile_app_keys' => ['mobile-test-key']]);
    [$employee, $user] = createClockingEmployeeUser($this->organisation);
    $timezone          = $this->organisation->timezone?->name ?? config('app.timezone');
    $yesterday         = now($timezone)->subDay()->toDateString();
    $headers           = ['X-Aiku-App-Key' => 'mobile-test-key'];
    Sanctum::actingAs($user, ['mobile']);

    $adjustmentId = post(route('grp.api.mobile.clocking.adjustments.store'), [
        'date'               => $yesterday,
        'requested_start_at' => '09:00',
        'requested_end_at'   => '17:30',
        'reason'             => 'Phone battery was flat',
    ], $headers)
        ->assertCreated()
        ->assertJsonPath('data.employee_id', $employee->id)
        ->assertJsonPath('data.date', $yesterday)
        ->assertJsonPath('data.requested_start_time_local', '09:00')
        ->assertJsonPath('data.requested_end_time_local', '17:30')
        ->assertJsonPath('data.status', AttendanceAdjustmentStatusEnum::PENDING->value)
        ->json('data.id');

    getJson(route('grp.api.mobile.clocking.adjustments.index'), $headers)
        ->assertOk()
        ->assertJsonCount(1, 'data.adjustments')
        ->assertJsonPath('data.adjustments.0.id', $adjustmentId)
        ->assertJsonPath('data.adjustments.0.status_label', __('Pending'))
        ->assertJsonPath('data.adjustments.0.requested_start_time_local', '09:00');

    deleteJson(route('grp.api.mobile.clocking.adjustments.cancel', $adjustmentId), [], $headers)
        ->assertOk()
        ->assertJsonPath('data.cancelled', true);

    expect(AttendanceAdjustment::find($adjustmentId))->toBeNull();
});

test('a clocking adjustment from the app needs the check out after the check in', function () {
    config(['app.mobile_app_keys' => ['mobile-test-key']]);
    [$employee, $user] = createClockingEmployeeUser($this->organisation);
    Sanctum::actingAs($user, ['mobile']);

    post(route('grp.api.mobile.clocking.adjustments.store'), [
        'date'               => now()->subDay()->toDateString(),
        'requested_start_at' => '17:00',
        'requested_end_at'   => '09:00',
        'reason'             => 'Swapped times',
    ], ['X-Aiku-App-Key' => 'mobile-test-key'])->assertUnprocessable()->assertJsonValidationErrors('requested_end_at');

    expect(AttendanceAdjustment::where('employee_id', $employee->id)->exists())->toBeFalse();
});

test('staff can not cancel someone else\'s clocking adjustment, or one already decided, from the app', function () {
    config(['app.mobile_app_keys' => ['mobile-test-key']]);
    [$owner, $ownerUser] = createClockingEmployeeUser($this->organisation);
    [, $other]           = createClockingEmployeeUser($this->organisation);
    $headers             = ['X-Aiku-App-Key' => 'mobile-test-key'];
    $adjustment          = StoreAttendanceAdjustment::make()->action($owner, [
        'date'               => now()->subDays(2)->toDateString(),
        'requested_start_at' => '08:00',
        'requested_end_at'   => '16:00',
        'reason'             => 'Missed the clock',
    ]);

    Sanctum::actingAs($other, ['mobile']);
    deleteJson(route('grp.api.mobile.clocking.adjustments.cancel', $adjustment->id), [], $headers)->assertForbidden();
    expect(AttendanceAdjustment::find($adjustment->id))->not->toBeNull();

    $adjustment->update(['status' => AttendanceAdjustmentStatusEnum::APPROVED]);
    Sanctum::actingAs($ownerUser, ['mobile']);
    deleteJson(route('grp.api.mobile.clocking.adjustments.cancel', $adjustment->id), [], $headers)
        ->assertUnprocessable()
        ->assertJsonValidationErrors('status');
});

test('staff request overtime from the app, see it listed with the overtime types and change it while pending', function () {
    config(['app.mobile_app_keys' => ['mobile-test-key']]);
    [$employee, $user] = createClockingEmployeeUser($this->organisation);
    $overtimeType      = createMobileOvertimeType($this->organisation);
    $headers           = ['X-Aiku-App-Key' => 'mobile-test-key'];
    Sanctum::actingAs($user, ['mobile']);

    $overtimeRequestId = postJson(route('grp.api.mobile.clocking.overtime_requests.store'), mobileOvertimeRequestBody($overtimeType, 90), $headers)
        ->assertCreated()
        ->assertJsonPath('data.overtime_type_id', $overtimeType->id)
        ->assertJsonPath('data.overtime_type_name', $overtimeType->name)
        ->assertJsonPath('data.requested_duration_minutes', 90)
        ->assertJsonPath('data.recorded_duration_minutes', null)
        ->assertJsonPath('data.status', 'pending')
        ->assertJsonPath('data.status_label', __('Pending'))
        ->json('data.id');

    $overtimeTab = getJson(route('grp.api.mobile.clocking.overtime_requests.index'), $headers)
        ->assertOk()
        ->assertJsonCount(1, 'data.overtime_requests')
        ->assertJsonPath('data.overtime_requests.0.id', $overtimeRequestId)
        ->json('data');

    expect(collect($overtimeTab['type_options'])->firstWhere('value', $overtimeType->id))->toBe(['value' => $overtimeType->id, 'label' => $overtimeType->name]);

    patchJson(route('grp.api.mobile.clocking.overtime_requests.update', $overtimeRequestId), mobileOvertimeRequestBody($overtimeType, 120), $headers)
        ->assertOk()
        ->assertJsonPath('data.requested_duration_minutes', 120);

    expect(OvertimeRequest::find($overtimeRequestId))
        ->employee_id->toBe($employee->id)
        ->requested_duration_minutes->toBe(120);
});

test('staff can not change someone else\'s overtime request from the app', function () {
    config(['app.mobile_app_keys' => ['mobile-test-key']]);
    [, $owner]    = createClockingEmployeeUser($this->organisation);
    [, $other]    = createClockingEmployeeUser($this->organisation);
    $overtimeType = createMobileOvertimeType($this->organisation);
    $headers      = ['X-Aiku-App-Key' => 'mobile-test-key'];

    Sanctum::actingAs($owner, ['mobile']);
    $overtimeRequestId = postJson(route('grp.api.mobile.clocking.overtime_requests.store'), mobileOvertimeRequestBody($overtimeType, 60), $headers)->json('data.id');

    Sanctum::actingAs($other, ['mobile']);
    patchJson(route('grp.api.mobile.clocking.overtime_requests.update', $overtimeRequestId), mobileOvertimeRequestBody($overtimeType, 600), $headers)->assertForbidden();
});

test('an overtime request from the app needs a duration', function () {
    config(['app.mobile_app_keys' => ['mobile-test-key']]);
    [$employee, $user] = createClockingEmployeeUser($this->organisation);
    Sanctum::actingAs($user, ['mobile']);

    postJson(route('grp.api.mobile.clocking.overtime_requests.store'), mobileOvertimeRequestBody(createMobileOvertimeType($this->organisation), 0), ['X-Aiku-App-Key' => 'mobile-test-key'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('requested_duration_minutes');

    expect(OvertimeRequest::where('employee_id', $employee->id)->exists())->toBeFalse();
});

test('the app calendar lists the organisation holidays of the month asked for', function () {
    config(['app.mobile_app_keys' => ['mobile-test-key']]);
    [, $user] = createClockingEmployeeUser($this->organisation);
    $headers  = ['X-Aiku-App-Key' => 'mobile-test-key'];
    $month    = Carbon::create(2040, rand(1, 11), 1);
    $label    = 'Mobile holiday '.uniqid();
    StoreHoliday::make()->action($this->organisation, [
        'type'  => HolidayTypeEnum::PUBLIC,
        'from'  => $month->copy()->day(10)->toDateString(),
        'to'    => $month->copy()->day(11)->toDateString(),
        'label' => $label,
    ]);
    StoreHoliday::make()->action($this->organisation, [
        'type'  => HolidayTypeEnum::PUBLIC,
        'from'  => $month->copy()->addMonth()->day(5)->toDateString(),
        'to'    => $month->copy()->addMonth()->day(5)->toDateString(),
        'label' => $label.' next month',
    ]);
    Sanctum::actingAs($user, ['mobile']);

    $calendar = getJson(route('grp.api.mobile.clocking.calendar', ['month' => $month->format('Y-m')]), $headers)
        ->assertOk()
        ->assertJsonPath('data.month', $month->format('Y-m'))
        ->json('data');

    $holidayLabels = collect($calendar['holidays'])->pluck('label', 'date');
    expect($holidayLabels->get($month->copy()->day(10)->toDateString()))->toContain($label)
        ->and($holidayLabels->get($month->copy()->day(11)->toDateString()))->toContain($label)
        ->and(collect($calendar['holidays'])->pluck('date')->every(fn (string $date) => str_starts_with($date, $month->format('Y-m'))))->toBeTrue()
        ->and(collect($calendar['holiday_ranges'])->firstWhere('label', $label))->toBe([
            'from'  => $month->copy()->day(10)->toDateString(),
            'to'    => $month->copy()->day(11)->toDateString(),
            'label' => $label,
        ])
        ->and(collect($calendar['holiday_ranges'])->firstWhere('label', $label.' next month'))->toBeNull();

    expect($calendar['leaves'])->toBe([]);

    getJson(route('grp.api.mobile.clocking.calendar', ['month' => '2040-13']), $headers)
        ->assertUnprocessable()
        ->assertJsonValidationErrors('month');
});

test('the app calendar shows the employee\'s own leave of the month', function () {
    config(['app.mobile_app_keys' => ['mobile-test-key']]);
    [, $user]  = createMobileLeaveEmployeeUser($this->organisation);
    $leaveType = createMobileMedicalLeaveType($this->organisation);
    $headers   = ['X-Aiku-App-Key' => 'mobile-test-key'];
    $day       = nextFreeMobileLeaveDay($this->organisation);
    Sanctum::actingAs($user, ['mobile']);
    $leaveId = requestMobileLeave($leaveType, $day)->assertCreated()->json('data.id');

    $calendar = getJson(route('grp.api.mobile.clocking.calendar', ['month' => $day->format('Y-m')]), $headers)
        ->assertOk()
        ->assertJsonCount(1, 'data.leaves')
        ->assertJsonPath('data.leaves.0.id', $leaveId)
        ->assertJsonPath('data.leaves.0.type', $leaveType->code)
        ->assertJsonPath('data.leaves.0.start_date', $day->toDateString())
        ->assertJsonPath('data.leaves.0.status', 'pending');

    expect($calendar->json('data.leaves.0.leave_type_value'))->toEqual(1);

    getJson(route('grp.api.mobile.clocking.calendar', ['month' => $day->copy()->addMonths(2)->format('Y-m')]), $headers)
        ->assertJsonPath('data.leaves', []);
});

test('mobile locations overview counts the locations of a warehouse the user may view', function () {
    [, $warehouse] = actingAsMobileLocationsViewer($this->group);
    createMobileLocationsFixture($warehouse);

    $response = getJson(route('grp.api.mobile.warehouses.locations_overview', $warehouse->id), ['X-Aiku-App-Key' => 'mobile-test-key'])
        ->assertOk()
        ->assertJsonPath('data.warehouse.id', $warehouse->id)
        ->assertJsonPath('data.warehouse.code', $warehouse->code);

    $stats = $warehouse->refresh()->stats;

    expect($response->json('data.number_locations'))->toBe((int) $stats->number_locations)
        ->and($response->json('data.number_areas'))->toBe((int) $stats->number_warehouse_areas)
        ->and($response->json('data.number_locations_empty'))->toBe((int) $stats->number_locations_stock_slots_all_empty);
});

test('mobile warehouse areas list each area of the warehouse', function () {
    [, $warehouse] = actingAsMobileLocationsViewer($this->group);
    [$area] = createMobileLocationsFixture($warehouse);

    $areas = collect(getJson(route('grp.api.mobile.warehouses.areas', $warehouse->id), ['X-Aiku-App-Key' => 'mobile-test-key'])
        ->assertOk()
        ->json('data'));

    expect($areas->firstWhere('id', $area->id))->toMatchArray([
        'code' => $area->code,
        'name' => $area->name,
    ]);
});

test('mobile locations list searches by code and filters by area', function () {
    [, $warehouse] = actingAsMobileLocationsViewer($this->group);
    [$area, $stocked, $empty, $prefix] = createMobileLocationsFixture($warehouse);

    getJson(route('grp.api.mobile.warehouses.locations', ['warehouse' => $warehouse->id, 'q' => $prefix]), ['X-Aiku-App-Key' => 'mobile-test-key'])
        ->assertOk()
        ->assertJsonPath('meta.total', 2)
        ->assertJsonPath('data.0.code', $stocked->code)
        ->assertJsonPath('data.0.area.code', $area->code)
        ->assertJsonPath('data.0.max_weight', 120)
        ->assertJsonPath('data.1.code', $empty->code);

    getJson(route('grp.api.mobile.warehouses.locations', ['warehouse' => $warehouse->id, 'area_id' => $area->id, 'q' => $prefix.'-02']), ['X-Aiku-App-Key' => 'mobile-test-key'])
        ->assertOk()
        ->assertJsonPath('meta.total', 1)
        ->assertJsonPath('data.0.id', $empty->id);
});

test('mobile locations list combines several areas, statuses, scopes and occupancies', function () {
    [, $warehouse] = actingAsMobileLocationsViewer($this->group);
    [$area, $stocked, $empty, $prefix] = createMobileLocationsFixture($warehouse);
    [$otherArea, $otherStocked] = createMobileLocationsFixture($warehouse);
    $headers = ['X-Aiku-App-Key' => 'mobile-test-key'];

    $bothAreas = collect(getJson(route('grp.api.mobile.warehouses.locations', ['warehouse' => $warehouse->id, 'area_ids' => [$area->id, $otherArea->id], 'per_page' => 150]), $headers)
        ->assertOk()
        ->json('data'))->pluck('id');

    expect($bothAreas)->toContain($stocked->id, $empty->id, $otherStocked->id)
        ->and($bothAreas)->toHaveCount(Location::whereIn('warehouse_area_id', [$area->id, $otherArea->id])->count());

    getJson(route('grp.api.mobile.warehouses.locations', ['warehouse' => $warehouse->id, 'q' => $prefix, 'statuses' => ['operational'], 'scopes' => ['stocks', 'dropshipping']]), $headers)
        ->assertOk()
        ->assertJsonPath('meta.total', Location::where('code', 'like', $prefix.'%')->where('status', 'operational')->where(fn ($query) => $query->where('allow_stocks', true)->orWhere('allow_dropshipping', true))->count());

    getJson(route('grp.api.mobile.warehouses.locations', ['warehouse' => $warehouse->id, 'q' => $prefix, 'occupancies' => ['goods_out', 'keep_empty_org_stocks']]), $headers)
        ->assertOk()
        ->assertJsonPath('meta.total', 0);

    getJson(route('grp.api.mobile.warehouses.locations', ['warehouse' => $warehouse->id, 'area_ids' => [999999999]]), $headers)
        ->assertUnprocessable();
});

test('mobile locations list pages like the web table, with at least the web minimum per page', function () {
    [, $warehouse] = actingAsMobileLocationsViewer($this->group);
    [, $stocked, $empty, $prefix] = createMobileLocationsFixture($warehouse);

    getJson(route('grp.api.mobile.warehouses.locations', ['warehouse' => $warehouse->id, 'q' => $prefix, 'per_page' => 1]), ['X-Aiku-App-Key' => 'mobile-test-key'])
        ->assertOk()
        ->assertJsonPath('meta.per_page', config('ui.table.min_records_per_page'))
        ->assertJsonPath('meta.total', 2)
        ->assertJsonPath('data.0.id', $stocked->id)
        ->assertJsonPath('data.1.id', $empty->id);

    getJson(route('grp.api.mobile.warehouses.locations', ['warehouse' => $warehouse->id, 'q' => $prefix, 'sort' => '-code']), ['X-Aiku-App-Key' => 'mobile-test-key'])
        ->assertOk()
        ->assertJsonPath('data.0.id', $empty->id);
});

test('mobile locations list filters empty and partially empty locations', function () {
    [, $warehouse] = actingAsMobileLocationsViewer($this->group);
    [, $stocked, $empty, $prefix] = createMobileLocationsFixture($warehouse);
    $stocked->stats()->update(['number_org_stock_slots' => 3, 'number_empty_stock_slots' => 1]);
    $empty->stats()->update(['number_org_stock_slots' => 2, 'number_empty_stock_slots' => 2]);

    $url = fn (string $occupancy) => route('grp.api.mobile.warehouses.locations', ['warehouse' => $warehouse->id, 'q' => $prefix, 'occupancy' => $occupancy]);

    getJson($url('empty'), ['X-Aiku-App-Key' => 'mobile-test-key'])
        ->assertOk()
        ->assertJsonPath('meta.total', 1)
        ->assertJsonPath('data.0.id', $empty->id);

    getJson($url('partially_empty'), ['X-Aiku-App-Key' => 'mobile-test-key'])
        ->assertOk()
        ->assertJsonPath('meta.total', 1)
        ->assertJsonPath('data.0.id', $stocked->id)
        ->assertJsonPath('data.0.number_org_stock_slots', 3)
        ->assertJsonPath('data.0.number_empty_stock_slots', 1);
});

test('mobile location shows the stock kept in it', function () {
    [, $warehouse] = actingAsMobileLocationsViewer($this->group);
    [, $stocked] = createMobileLocationsFixture($warehouse);
    $locationOrgStock = $stocked->locationOrgStocks()->with('orgStock')->first();

    getJson(route('grp.api.mobile.locations.show', $stocked->id), ['X-Aiku-App-Key' => 'mobile-test-key'])
        ->assertOk()
        ->assertJsonPath('data.code', $stocked->code)
        ->assertJsonCount(1, 'data.org_stocks')
        ->assertJsonPath('data.org_stocks.0.code', $locationOrgStock->orgStock->code)
        ->assertJsonPath('data.org_stocks.0.location_org_stock_id', $locationOrgStock->id)
        ->assertJsonPath('data.org_stocks.0.quantity', '30')
        ->assertJsonPath('data.org_stocks.0.type', 'picking')
        ->assertJsonPath('data.org_stocks.0.min_stock', 12)
        ->assertJsonPath('data.org_stocks.0.max_stock', 48);
});

test('mobile locations are forbidden without location access in that warehouse', function () {
    config(['app.mobile_app_keys' => ['mobile-test-key']]);
    $warehouse = createWarehouse();
    [, $stocked] = createMobileLocationsFixture($warehouse);
    Sanctum::actingAs(createMobileAppUser($this->group), ['mobile']);

    getJson(route('grp.api.mobile.warehouses.locations_overview', $warehouse->id), ['X-Aiku-App-Key' => 'mobile-test-key'])->assertForbidden();
    getJson(route('grp.api.mobile.warehouses.areas', $warehouse->id), ['X-Aiku-App-Key' => 'mobile-test-key'])->assertForbidden();
    getJson(route('grp.api.mobile.warehouses.locations', $warehouse->id), ['X-Aiku-App-Key' => 'mobile-test-key'])->assertForbidden();
    getJson(route('grp.api.mobile.locations.show', $stocked->id), ['X-Aiku-App-Key' => 'mobile-test-key'])->assertForbidden();
});

test('mobile locations need the app key', function () {
    [, $warehouse] = actingAsMobileLocationsViewer($this->group);

    getJson(route('grp.api.mobile.warehouses.locations', $warehouse->id))->assertNotFound();
});


test('mobile locations overview reports the empty location removal for the warehouse', function () {
    [, $warehouse] = actingAsMobileLocationsViewer($this->group);
    [, , $empty] = createMobileLocationsFixture($warehouse);
    createMobileEmptyLocationLink($warehouse, $empty);

    getJson(route('grp.api.mobile.warehouses.locations_overview', $warehouse->id), ['X-Aiku-App-Key' => 'mobile-test-key'])
        ->assertOk()
        ->assertJsonPath('data.empty_locations_removal.enabled', false)
        ->assertJsonPath('data.empty_locations_removal.can_edit', false)
        ->assertJsonPath('data.empty_locations_removal.to_remove', DisassociateEmptyLocationOrgStocks::candidatesQuery($warehouse)->count())
        ->assertJsonPath('data.empty_locations_removal.max_per_run', DisassociateEmptyLocationOrgStocks::MAX_PER_RUN);
});

test('mobile empty location org stocks list the links the next run would remove', function () {
    [, $warehouse] = actingAsMobileLocationsViewer($this->group);
    [, , $empty] = createMobileLocationsFixture($warehouse);
    [, $orgStock] = createMobileEmptyLocationLink($warehouse, $empty);

    $rows = collect(getJson(route('grp.api.mobile.warehouses.empty_location_org_stocks', [$warehouse->id, 'q' => $empty->code]), ['X-Aiku-App-Key' => 'mobile-test-key'])
        ->assertOk()
        ->assertJsonPath('meta.total', 1)
        ->json('data'));

    expect($rows->first())->toMatchArray([
        'code'          => $orgStock->code,
        'name'          => $orgStock->name,
        'location_code' => $empty->code,
    ]);
});

test('mobile empty locations removal needs stock edit rights', function () {
    [, $warehouse] = actingAsMobileLocationsViewer($this->group);

    patchJson(route('grp.api.mobile.warehouses.empty_locations_removal.update', $warehouse->id), ['enabled' => true], ['X-Aiku-App-Key' => 'mobile-test-key'])
        ->assertForbidden();

    expect($warehouse->refresh()->isAutoDisassociatingEmptyLocations())->toBeFalse();
});

test('mobile empty locations removal is switched on and the removed links are listed', function () {
    [$user, $warehouse] = actingAsMobileLocationsViewer($this->group);
    $user->assignRole(RolesEnum::getRoleName(RolesEnum::STOCK_CONTROLLER->value, $warehouse));
    SetUserAuthorisedModels::run($user);
    [, , $empty] = createMobileLocationsFixture($warehouse);
    [, $orgStock] = createMobileEmptyLocationLink($warehouse, $empty);

    patchJson(route('grp.api.mobile.warehouses.empty_locations_removal.update', $warehouse->id), ['enabled' => true], ['X-Aiku-App-Key' => 'mobile-test-key'])
        ->assertOk()
        ->assertJsonPath('data.enabled', true)
        ->assertJsonPath('data.can_edit', true);

    expect($warehouse->refresh()->isAutoDisassociatingEmptyLocations())->toBeTrue();

    DisassociateEmptyLocationOrgStocks::run();

    $rows = collect(getJson(route('grp.api.mobile.warehouses.removed_empty_location_org_stocks', [$warehouse->id, 'q' => $empty->code]), ['X-Aiku-App-Key' => 'mobile-test-key'])
        ->assertOk()
        ->json('data'));

    expect($rows->pluck('code'))->toContain($orgStock->code)
        ->and($rows->first()['location_code'])->toBe($empty->code)
        ->and($rows->first()['date'])->not->toBeNull();
});

test('the app profile shows the job title and the organisation of the current employment', function () {
    config(['app.mobile_app_keys' => ['mobile-test-key']]);
    [$employee, $user] = createClockingEmployeeUser($this->organisation);
    $employee->update(['job_title' => 'Warehouse picker']);
    Sanctum::actingAs($user, ['mobile']);

    getJson(route('grp.api.mobile.profile'), ['X-Aiku-App-Key' => 'mobile-test-key'])
        ->assertOk()
        ->assertJsonPath('data.id', $user->id)
        ->assertJsonPath('data.username', $user->username)
        ->assertJsonPath('data.position', 'Warehouse picker')
        ->assertJsonPath('data.job_title', 'Warehouse picker')
        ->assertJsonPath('data.organisation.code', $this->organisation->code)
        ->assertJsonPath('data.organisation.name', $this->organisation->name)
        ->assertJsonMissingPath('data.salary');
});

test('the app profile of a user with no employment has no position or organisation', function () {
    config(['app.mobile_app_keys' => ['mobile-test-key']]);
    Sanctum::actingAs(createMobileAppUser($this->group), ['mobile']);

    getJson(route('grp.api.mobile.profile'), ['X-Aiku-App-Key' => 'mobile-test-key'])
        ->assertOk()
        ->assertJsonPath('data.organisation', null)
        ->assertJsonPath('data.job_title', null);
});


test('mobile stock movement reasons are the ones the web offers', function () {
    actingAsMobileLocationsViewer($this->group);

    getJson(route('grp.api.mobile.stock_movement_reasons'), ['X-Aiku-App-Key' => 'mobile-test-key'])
        ->assertOk()
        ->assertJsonPath('data.transfer', OrgStockMovementReasonEnum::withLabels(OrgStockMovementReasonEnum::transferReason()))
        ->assertJsonPath('data.decrease.count_short', OrgStockMovementReasonEnum::COUNT_SHORT->label());
});

test('mobile location audit records the web audit movement for each SKO counted', function () {
    [$user, $warehouse] = actingAsMobileWarehouseUser($this->group, RolesEnum::STOCK_CONTROLLER);
    [, $stocked] = createMobileLocationsFixture($warehouse);
    $link = $stocked->locationOrgStocks()->first();

    postJson(route('grp.api.mobile.locations.audit', $stocked->id), ['counts' => [[
        'location_org_stock_id' => $link->id,
        'quantity'              => 25,
        'reason'                => OrgStockMovementReasonEnum::COUNT_SHORT->value,
        'note'                  => 'Counted in the app',
    ]]], ['X-Aiku-App-Key' => 'mobile-test-key'])
        ->assertOk()
        ->assertJsonPath('data.can.edit_stocks', true)
        ->assertJsonPath('data.can.edit_locations', false);

    $movement = OrgStockMovement::where('org_stock_id', $link->org_stock_id)->where('type', OrgStockMovementTypeEnum::AUDIT)->latest('id')->first();

    expect((float) $link->refresh()->quantity)->toBe(25.0)
        ->and($link->audited_at)->not->toBeNull()
        ->and($movement->reason)->toBe(OrgStockMovementReasonEnum::COUNT_SHORT)
        ->and($movement->note)->toBe('Counted in the app')
        ->and($movement->user_id)->toBe($user->id)
        ->and((float) $movement->quantity)->toBe(-5.0);
});

test('mobile location audit needs stock edit rights and SKOs of that location', function () {
    [$user, $warehouse] = actingAsMobileLocationsViewer($this->group);
    [, $stocked, $empty] = createMobileLocationsFixture($warehouse);
    $link = $stocked->locationOrgStocks()->first();
    $count = ['counts' => [['location_org_stock_id' => $link->id, 'quantity' => 1, 'reason' => 'recount']]];

    postJson(route('grp.api.mobile.locations.audit', $stocked->id), $count, ['X-Aiku-App-Key' => 'mobile-test-key'])->assertForbidden();

    grantMobileWarehouseRole($user, $warehouse, RolesEnum::STOCK_CONTROLLER);
    postJson(route('grp.api.mobile.locations.audit', $empty->id), $count, ['X-Aiku-App-Key' => 'mobile-test-key'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('counts.0.location_org_stock_id');
});

test('mobile SKO in location settings use the web update and need stock edit rights', function () {
    [$user, $warehouse] = actingAsMobileLocationsViewer($this->group);
    [, $stocked] = createMobileLocationsFixture($warehouse);
    $link = $stocked->locationOrgStocks()->first();
    $settings = ['type' => LocationStockTypeEnum::STORING->value, 'min_stock' => 5, 'max_stock' => 60, 'notes' => 'Top shelf'];

    patchJson(route('grp.api.mobile.location_org_stocks.update', $link->id), $settings, ['X-Aiku-App-Key' => 'mobile-test-key'])->assertForbidden();

    grantMobileWarehouseRole($user, $warehouse, RolesEnum::STOCK_CONTROLLER);
    patchJson(route('grp.api.mobile.location_org_stocks.update', $link->id), $settings, ['X-Aiku-App-Key' => 'mobile-test-key'])
        ->assertOk()
        ->assertJsonPath('data.org_stocks.0.notes', 'Top shelf')
        ->assertJsonPath('data.org_stocks.0.min_stock', 5)
        ->assertJsonPath('data.org_stocks.0.max_stock', 60);

    expect($link->refresh()->type)->toBe(LocationStockTypeEnum::STORING);
});

test('mobile move and remove follow the web location edit rights', function () {
    [, $warehouse] = actingAsMobileWarehouseUser($this->group, RolesEnum::STOCK_CONTROLLER);
    [, $stocked, $empty] = createMobileLocationsFixture($warehouse);
    $orgStockId = $stocked->locationOrgStocks()->value('org_stock_id');
    $headers = ['X-Aiku-App-Key' => 'mobile-test-key'];

    postJson(route('grp.api.mobile.locations.move_all', $stocked->id), ['location_id' => $empty->id, 'reason' => 'transfer'], $headers)->assertForbidden();
    postJson(route('grp.api.mobile.locations.move_some', $stocked->id), ['location_id' => $empty->id, 'reason' => 'transfer', 'org_stocks' => [['org_stock_id' => $orgStockId, 'quantity' => 1]]], $headers)->assertForbidden();
    postJson(route('grp.api.mobile.locations.remove_org_stocks', $stocked->id), ['org_stock_ids' => [$orgStockId]], $headers)->assertForbidden();
});

test('mobile move some, move all and remove use the web location actions', function () {
    [, $warehouse] = actingAsMobileWarehouseUser($this->group, RolesEnum::WAREHOUSE_ADMIN);
    [, $stocked, $empty] = createMobileLocationsFixture($warehouse);
    $orgStockId = $stocked->locationOrgStocks()->value('org_stock_id');
    $headers = ['X-Aiku-App-Key' => 'mobile-test-key'];
    $quantityIn = fn (Location $location) => (float) LocationOrgStock::where('location_id', $location->id)->where('org_stock_id', $orgStockId)->value('quantity');

    postJson(route('grp.api.mobile.locations.move_some', $stocked->id), ['location_id' => $empty->id, 'reason' => 'transfer', 'org_stocks' => [['org_stock_id' => $orgStockId, 'quantity' => 10]]], $headers)
        ->assertOk()
        ->assertJsonPath('data.id', $stocked->id);
    expect($quantityIn($stocked))->toBe(20.0)->and($quantityIn($empty))->toBe(10.0);

    postJson(route('grp.api.mobile.locations.move_all', $empty->id), ['location_id' => $stocked->id, 'reason' => 'transfer', 'remove_after_move' => true], $headers)
        ->assertOk()
        ->assertJsonCount(0, 'data.org_stocks');
    expect($quantityIn($stocked))->toBe(30.0)
        ->and(LocationOrgStock::where('location_id', $empty->id)->exists())->toBeFalse();

    postJson(route('grp.api.mobile.locations.remove_org_stocks', $stocked->id), ['org_stock_ids' => [$orgStockId]], $headers)
        ->assertOk()
        ->assertJsonPath('removed', 1);
    expect(LocationOrgStock::where('location_id', $stocked->id)->exists())->toBeFalse();

    postJson(route('grp.api.mobile.locations.move_all', $stocked->id), ['location_id' => $stocked->id, 'reason' => 'transfer'], $headers)
        ->assertUnprocessable()
        ->assertJsonValidationErrors('location_id');
});

test('mobile location stock movements are the web movements tab, newest first', function () {
    [$user, $warehouse] = actingAsMobileWarehouseUser($this->group, RolesEnum::STOCK_CONTROLLER);
    [, $stocked] = createMobileLocationsFixture($warehouse);
    $link = $stocked->locationOrgStocks()->with('orgStock')->first();

    postJson(route('grp.api.mobile.locations.audit', $stocked->id), ['counts' => [[
        'location_org_stock_id' => $link->id,
        'quantity'              => 28,
        'reason'                => OrgStockMovementReasonEnum::COUNT_SHORT->value,
    ]]], ['X-Aiku-App-Key' => 'mobile-test-key'])->assertOk();

    getJson(route('grp.api.mobile.locations.stock_movements', $stocked->id), ['X-Aiku-App-Key' => 'mobile-test-key'])
        ->assertOk()
        ->assertJsonPath('data.0.type', OrgStockMovementTypeEnum::AUDIT->value)
        ->assertJsonPath('data.0.reason', OrgStockMovementReasonEnum::COUNT_SHORT->value)
        ->assertJsonPath('data.0.org_stock_code', $link->orgStock->code)
        ->assertJsonPath('data.0.user_name', $user->contact_name ?: $user->username)
        ->assertJsonPath('meta.total', OrgStockMovement::where('location_id', $stocked->id)->count());
});

test('mobile location detail lists its pallets and its movements need location access', function () {
    [, $warehouse] = actingAsMobileLocationsViewer($this->group);
    [, $stocked] = createMobileLocationsFixture($warehouse);

    getJson(route('grp.api.mobile.locations.show', $stocked->id), ['X-Aiku-App-Key' => 'mobile-test-key'])
        ->assertOk()
        ->assertJsonPath('data.pallets', []);

    Sanctum::actingAs(createMobileAppUser($this->group), ['mobile']);
    getJson(route('grp.api.mobile.locations.stock_movements', $stocked->id), ['X-Aiku-App-Key' => 'mobile-test-key'])->assertForbidden();
});

test('mobile SKO families come from the web families table with the state counts', function () {
    [, $warehouse] = actingAsMobileWarehouseUser($this->group, RolesEnum::STOCK_CONTROLLER);
    $stockFamily    = \App\Actions\Goods\StockFamily\StoreStockFamily::make()->action($warehouse->group, \App\Models\Goods\StockFamily::factory()->definition());
    $orgStockFamily = \App\Actions\Inventory\OrgStockFamily\StoreOrgStockFamily::make()->action($warehouse->organisation, $stockFamily, []);
    $orgStockFamily->update(['state' => \App\Enums\Inventory\OrgStockFamily\OrgStockFamilyStateEnum::ACTIVE]);
    $headers = ['X-Aiku-App-Key' => 'mobile-test-key'];

    $rows = collect(getJson(route('grp.api.mobile.warehouses.org_stock_families', [$warehouse->id, 'state' => 'active', 'q' => $orgStockFamily->code]), $headers)
        ->assertOk()
        ->assertJsonStructure(['data', 'meta' => ['total'], 'counts' => ['in_process', 'active', 'discontinuing', 'discontinued']])
        ->json('data'));

    expect($rows->firstWhere('code', $orgStockFamily->code))->toMatchArray(['id' => $orgStockFamily->id, 'state' => 'active']);

    getJson(route('grp.api.mobile.warehouses.org_stock_families.skos', [$warehouse->id, $orgStockFamily->id]), $headers)
        ->assertOk()
        ->assertJsonPath('family.id', $orgStockFamily->id)
        ->assertJsonPath('family.code', $orgStockFamily->code);
});

test('mobile SKO families need inventory view rights', function () {
    [, $warehouse] = actingAsMobileLocationsViewer($this->group);
    Sanctum::actingAs(createMobileAppUser($this->group), ['mobile']);

    getJson(route('grp.api.mobile.warehouses.org_stock_families', $warehouse->id), ['X-Aiku-App-Key' => 'mobile-test-key'])->assertForbidden();
});
