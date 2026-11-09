<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 01 Jul 2026 00:00:00 Central Indonesia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

/** @noinspection PhpUnhandledExceptionInspection */

namespace Tests\Feature;

use App\Actions\Accounting\Invoice\StoreInvoice;
use App\Actions\Ordering\Order\StoreOrder;
use App\Actions\Ordering\Order\UpdateState\SubmitOrder;
use App\Actions\SysAdmin\User\SetUserAuthorisedModels;
use App\Enums\SysAdmin\Authorisation\RolesEnum;
use App\Models\Accounting\Invoice;
use App\Models\Chat\ChatAiAccountUsageSample;
use App\Models\Ordering\Order;
use App\Models\SysAdmin\Group;
use App\Models\SysAdmin\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Laravel\Sanctum\PersonalAccessToken;
use Laravel\Sanctum\Sanctum;
use PragmaRX\Google2FAQRCode\Google2FA;

use function Pest\Laravel\getJson;
use function Pest\Laravel\postJson;

function createMobileAppUser(Group $group, array $attributes = []): User
{
    $user = User::factory()->create(array_merge([
        'group_id' => $group->id,
        'password' => 'mobile-app-password',
        'status'   => true,
    ], $attributes));
    $user->stats()->create();

    return $user;
}

function postMobileLogin(array $body, array $headers = ['X-Aiku-App-Key' => 'mobile-test-key']): TestResponse
{
    config(['app.mobile_app_keys' => ['mobile-test-key']]);

    return postJson(route('grp.api.mobile.login'), $body, $headers);
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

test('mobile login sends a user who must use two factor but has not set it up to the web', function () {
    $user = createMobileAppUser($this->group, ['is_two_factor_required' => true]);

    postMobileLogin([
        'username'    => $user->username,
        'password'    => 'mobile-app-password',
        'device_name' => 'Pixel 8 · Android 15',
    ])->assertUnprocessable()->assertJsonValidationErrors(['username' => 'Set up two-factor authentication on the web first.']);
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
        ->assertJsonPath('data.0.can.view_goods_out', true)
        ->assertJsonPath('data.0.can.pick_and_pack', true)
        ->assertJsonPath('data.0.can.supervise_goods_out', false)
        ->assertJsonPath('data.0.can.view_locations', true)
        ->assertJsonPath('data.0.can.edit_locations', false)
        ->assertJsonPath('data.0.can.view_inventory', false)
        ->assertJsonPath('data.0.can.move_stock', false)
        ->assertJsonPath('data.0.can.view_goods_in', false)
        ->assertJsonPath('data.0.can.book_in', false);
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
        ->assertJsonPath('data.0.can.view_goods_out', false)
        ->assertJsonPath('data.0.can.pick_and_pack', false);
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
