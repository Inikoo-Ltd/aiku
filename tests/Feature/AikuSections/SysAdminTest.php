<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 26 Nov 2024 21:34:34 Central Indonesia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

/** @noinspection PhpUnhandledExceptionInspection */

use App\Actions\Analytics\GetSectionRoute;
use App\Actions\Catalogue\Shop\StoreShop;
use App\Actions\Helpers\Address\HydrateAddress;
use App\Actions\Helpers\Address\ParseCountryID;
use App\Actions\Helpers\Avatars\GetDiceBearAvatar;
use App\Actions\Helpers\Country\UI\GetAddressData;
use App\Actions\Helpers\Country\UI\GetCountriesOptions;
use App\Actions\Helpers\Currency\UI\GetCurrenciesOptions;
use App\Actions\Helpers\Language\UI\GetLanguagesOptions;
use App\Actions\Helpers\Media\HydrateMedia;
use App\Actions\Helpers\TimeZone\UI\GetTimeZonesOptions;
use App\Actions\HumanResources\Employee\StoreEmployee;
use App\Actions\Maintenance\SysAdmin\RepairUsersAdminsAuth;
use App\Actions\SysAdmin\Admin\StoreAdmin;
use App\Actions\SysAdmin\Group\HydrateGroup;
use App\Actions\SysAdmin\Group\StoreGroup;
use App\Actions\SysAdmin\Group\UpdateGroup;
use App\Actions\SysAdmin\Guest\DeleteGuest;
use App\Actions\SysAdmin\Guest\HydrateGuest;
use App\Actions\SysAdmin\Guest\StoreGuest;
use App\Actions\SysAdmin\Guest\UpdateGuest;
use App\Actions\SysAdmin\Organisation\HydrateOrganisations;
use App\Actions\SysAdmin\Organisation\StoreOrganisation;
use App\Actions\SysAdmin\Organisation\UpdateOrganisation;
use App\Actions\SysAdmin\User\HydrateUser;
use App\Actions\SysAdmin\User\Search\ReindexUserSearch;
use App\Actions\SysAdmin\User\UpdateUser;
use App\Actions\SysAdmin\User\UpdateUserOrganisationPseudoJobPositions;
use App\Actions\SysAdmin\User\UpdateUserStatus;
use App\Actions\SysAdmin\User\UserAddRoles;
use App\Actions\SysAdmin\User\UserRemoveRoles;
use App\Actions\SysAdmin\User\UserSyncRoles;
use App\Enums\Analytics\AikuSection\AikuSectionEnum;
use App\Enums\SysAdmin\Organisation\OrganisationTypeEnum;
use App\Models\Analytics\AikuScopedSection;
use App\Models\Catalogue\Shop;
use App\Models\Helpers\Address;
use App\Models\Helpers\Country;
use App\Models\Helpers\Currency;
use App\Models\Helpers\Media;
use App\Models\HumanResources\Employee;
use App\Models\HumanResources\JobPosition;
use App\Models\SysAdmin\Admin;
use App\Models\SysAdmin\Group;
use App\Models\SysAdmin\Guest;
use App\Models\SysAdmin\Organisation;
use App\Models\SysAdmin\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Inertia\Testing\AssertableInertia;
use Laravel\Sanctum\PersonalAccessToken;
use Laravel\Sanctum\Sanctum;

use function Pest\Laravel\{get};
use function Pest\Laravel\{actingAs};

beforeAll(function () {
    loadDB();
    Sanctum::usePersonalAccessTokenModel(PersonalAccessToken::class);
});

beforeEach(function () {
    GetDiceBearAvatar::mock()
        ->shouldReceive('handle')
        ->andReturn(Storage::disk('art')->get('icons/shapes.svg'));


    Config::set(
        'inertia.testing.page_paths',
        [resource_path('js/Pages/Grp')]
    );

});

test('create group', function () {
    $modelData = Group::factory()->definition();

    $modelData = array_merge($modelData, [
        'code' => 'TEST',
        'name' => 'Test Group',
    ]);

    $jobPositions = collect(config("blueprint.job_positions.positions"));


    $group = StoreGroup::make()->action($modelData);
    expect($group)->toBeInstanceOf(Group::class)
        ->and($group->roles()->count())->toBe(7)
        ->and($group->jobPositionCategories()->count())->toBe($jobPositions->count());

    return $group;
});

test('group scoped job positions', function (Group $group) {
    $jobPositions = collect(config("blueprint.job_positions.positions"));
    expect($group->jobPositions()->count())->toBe(5)
        ->and($group->jobPositionCategories()->count())->toBe($jobPositions->count());

    $this->artisan('group:seed-job-positions', [
        'group' => $group->slug,
    ])->assertSuccessful();

    expect($group->jobPositions()->count())->toBe(5)
        ->and($group->jobPositionCategories()->count())->toBe($jobPositions->count());
})->depends('create group');


test('set group logo by command', function (Group $group) {
    $this->artisan('group:logo', [
        'group' => $group->slug,
    ])->assertSuccessful();
})->depends('create group');

test('create group by command', function () {
    /** @var Currency $currency */
    $currency = Currency::where('code', 'USD')->firstOrFail();

    $this->artisan('group:create', [
        'code'          => 'TEST2',
        'name'          => 'Test Group',
        'currency_code' => $currency->code,
        'country_code'  => 'US'
    ])->assertSuccessful();
    $group = Group::where('code', 'TEST2')->firstOrFail();
    expect($group)->toBeInstanceOf(Group::class);

    return $group;
});

test('update group name', function (Group $group) {
    $group = UpdateGroup::make()->action($group, ['name' => 'Test Group 2']);
    expect($group->name)->toBe('Test Group 2');
})->depends('create group');


test('create a system admin', function () {
    $admin = StoreAdmin::make()->action(Admin::factory()->definition());
    $this->assertModelExists($admin);
});


test('create organisation type shop', function (Group $group) {
    $modelData = Organisation::factory()->definition();
    data_set($modelData, 'code', 'acme');
    data_set($modelData, 'type', OrganisationTypeEnum::SHOP);

    $address = new Address(Address::factory()->definition());
    data_set($modelData, 'address', $address->toArray());


    $organisation = StoreOrganisation::make()->action($group, $modelData);

    expect($organisation)->toBeInstanceOf(Organisation::class)
        ->and($organisation->address)->toBeInstanceOf(Address::class)
        ->and($organisation->roles()->count())->toBe(7)
        ->and($group->roles()->count())->toBe(14)
        ->and($organisation->accountingStats->number_org_payment_service_providers)->toBe(1)
        ->and($organisation->accountingStats->number_org_payment_service_providers_type_account)->toBe(1);

    return $organisation;
})->depends('create group');

test('set organisation logo by command', function (Organisation $organisation) {
    $this->artisan('org:logo', [
        'organisation' => $organisation->slug,
    ])->assertSuccessful();
})->depends('create organisation type shop');

test('create organisation by command', function (Group $group) {
    $this->artisan('org:create', [
        'group'         => $group->slug,
        'type'          => 'shop',
        'code'          => 'TEST',
        'email'         => 'a@example.com',
        'name'          => 'Test Organisation in group 2',
        'country_code'  => 'MY',
        'currency_code' => 'MYR',
        '--address'       => json_encode(Address::factory()->definition())
    ])->assertSuccessful();
    $organisation = Organisation::where('code', 'TEST')->firstOrFail();
    expect($organisation)->toBeInstanceOf(Organisation::class);

    return $organisation;
})->depends('create group by command');

test('update organisation name', function (Organisation $organisation) {
    $organisation = UpdateOrganisation::make()->action(
        $organisation,
        ['name' => 'Test New Organisation 2']
    );
    expect($organisation->name)->toBe('Test New Organisation 2');
})->depends('create organisation by command');

test('set organisation google key', function (Organisation $organisation) {
    $this->artisan('org:set-google-key', [
        'organisation'               => $organisation->slug,
        'google_cloud_client_id'     => '1234567890',
        'google_cloud_client_secret' => '1234567890',
        'google_drive_folder_key'    => '1234567890'
    ])->assertSuccessful();
})->depends('create organisation by command');

test('update organisation logo', function (Organisation $organisation) {
    Storage::fake('public');

    $fakeImage = UploadedFile::fake()->image('logo.jpg', 20, 20);

    $organisation = UpdateOrganisation::make()->action(
        $organisation,
        [
            'logo' => $fakeImage
        ]
    );
    $organisation->refresh();
    expect($organisation->images->count())->toBe(1)
        ->and($organisation->image->name)->toBe('logo.jpg');
})->depends('create organisation by command');


test('create guest', function (Group $group, Organisation $organisation) {
    app()->instance('group', $group);
    setPermissionsTeamId($group->id);

    $jobPosition1 = $group->jobPositions()->where('code', 'gp-sc')->first();
    $jobPosition2 = $group->jobPositions()->where('code', 'org-admin')->where('organisation_id', $organisation->id)->first();
    $jobPosition3 = $group->jobPositions()->where('code', 'sys-admin')->first();
    $guestData    = Guest::factory()->definition();
    data_set($guestData, 'user.username', 'hello');
    data_set($guestData, 'user.password', 'secret-password');
    data_set($guestData, 'phone', '+6281212121212');
    data_set(
        $guestData,
        'positions',
        [
            $jobPosition1->slug => [
                'slug'   => $jobPosition1->slug,
                'scopes' => []
            ],
            $jobPosition2->slug =>
                [
                    'slug'   => $jobPosition2->slug,
                    'scopes' => [
                        'organisations' => [
                            $organisation->slug
                        ]
                    ]
                ],
            $jobPosition3->slug => [
                'slug'   => $jobPosition3->slug,
                'scopes' => []
            ],
        ]
    );


    $guest = StoreGuest::make()->action(
        $group,
        $guestData
    );

    $user = $guest->getUser();
    $user->refresh();

    expect($guest)->toBeInstanceOf(Guest::class)
        ->and($user)->toBeInstanceOf(User::class)
        ->and($user->username)->toBe('hello')
        ->and($user->contact_name)->toBe($guest->contact_name)
        ->and($user->authorisedOrganisations()->count())->toBe(1)
        ->and($guest->phone)->toBe('+6281212121212')
        ->and($group->sysadminStats->number_guests)->toBe(1)
        ->and($group->sysadminStats->number_guests_status_active)->toBe(1)
        ->and($group->sysadminStats->number_users)->toBe(1)
        ->and($group->sysadminStats->number_users_status_active)->toBe(1)
        ->and($group->sysadminStats->number_users_status_inactive)->toBe(0);

    return $guest;
})->depends('create group', 'create organisation type shop');

test('SetUserAuthorisedModels command', function (Guest $guest) {
    $this->artisan('user:set_authorised_models', [
        'user' => $guest->getUser()->slug,
    ])->assertSuccessful();

    $user = $guest->getUser();
    expect($user->authorisedOrganisations()->count())->toBe(1);

    return $user;

})->depends('create guest');


test('UI index users (active)', function (User $user) {
    $this->withoutExceptionHandling();
    actingAs($user);

    $response = get(
        route(
            'grp.sysadmin.users.index'
        )
    );

    $response->assertInertia(function (AssertableInertia $page) {
        $page
            ->component('SysAdmin/Users')
            ->has('breadcrumbs', 3)
            ->has('title')
            ->has('data')
            ->has('pageHead');
    });

})->depends('SetUserAuthorisedModels command');

test('UI index users (suspended)', function (User $user) {
    $this->withoutExceptionHandling();
    actingAs($user);

    $response = get(
        route(
            'grp.sysadmin.users.suspended.index'
        )
    );

    $response->assertInertia(function (AssertableInertia $page) {
        $page
            ->component('SysAdmin/Users')
            ->has('breadcrumbs', 3)
            ->has('title')
            ->has('data')
            ->has('pageHead');
    });

})->depends('SetUserAuthorisedModels command');

test('UI index all users', function (User $user) {
    $this->withoutExceptionHandling();
    actingAs($user);

    $response = get(
        route(
            'grp.sysadmin.users.all.index'
        )
    );

    $response->assertInertia(function (AssertableInertia $page) {
        $page
            ->component('SysAdmin/Users')
            ->has('breadcrumbs', 3)
            ->has('title')
            ->has('data')
            ->has('pageHead');
    });

})->depends('SetUserAuthorisedModels command');

test('UI show dashboard org', function (User $user) {
    $this->withoutExceptionHandling();
    actingAs($user);

    /** @var Organisation $organisation */
    $organisation = $user->authorisedOrganisations()->first();

    $response = get(
        route(
            'grp.org.dashboard.show',
            [
                $organisation->slug
            ]
        )
    );

    $response->assertInertia(function (AssertableInertia $page) use ($organisation) {
        $page
            ->component('Dashboard/OrganisationDashboard')
            ->has('breadcrumbs', 1)
            ->has(
                'dashboard',
                fn (AssertableInertia $page) =>
                $page->has('interval_options')
                ->has('settings')
                ->where('currency_code', $organisation->currency->code)
                ->etc()
            );
    });

})->depends('SetUserAuthorisedModels command')->todo();


test('UI create shop', function (User $user) {
    $this->withoutExceptionHandling();
    actingAs($user);
    /** @var Organisation $organisation */
    $organisation = $user->authorisedOrganisations()->first();

    $response = get(
        route(
            'grp.org.shops.create',
            [
                $organisation->slug,
            ]
        )
    );
    $response->assertInertia(function (AssertableInertia $page) {
        $page
            ->component('CreateModel')
            ->has('title')
            ->has('breadcrumbs', 3)
            ->has('formData', 2)
            ->has('pageHead');
    });
})->depends('SetUserAuthorisedModels command');

test('UI edit shop', function (User $user) {
    $this->withoutExceptionHandling();
    actingAs($user);
    /** @var Organisation $organisation */
    $organisation = $user->authorisedOrganisations()->first();

    $shop     = StoreShop::make()->action($organisation, Shop::factory()->definition());


    $response = get(
        route(
            'grp.org.shops.show.settings.edit',
            [
                $organisation->slug,
                $shop->slug,
            ]
        )
    );
    $response->assertInertia(function (AssertableInertia $page) {
        $page
            ->component('EditModel')
            ->has('title')
            ->has('breadcrumbs', 3)
            ->has('formData')
            ->has('pageHead');
    });

    return $shop;
})->depends('SetUserAuthorisedModels command');

test('UI show shop', function (User $user, Shop $shop) {
    $this->withoutExceptionHandling();
    actingAs($user);

    $response = get(
        route(
            'grp.org.shops.show.dashboard.show',
            [
                $shop->organisation->slug,
                $shop->slug
            ]
        )
    );
    $response->assertInertia(function (AssertableInertia $page) {
        $page
            ->component('Org/Catalogue/Shop')
            ->has('title')
            ->has('breadcrumbs', 2)
            ->has('flatTreeMaps')
            ->has('tabs')
            ->has('pageHead');
    });
})->depends('SetUserAuthorisedModels command', 'UI edit shop');

test('UI index shop', function (User $user) {
    $this->withoutExceptionHandling();
    actingAs($user);
    /** @var Organisation $organisation */
    $organisation = $user->authorisedOrganisations()->first();

    $response = get(
        route(
            'grp.org.shops.index',
            [
                $organisation->slug,
            ]
        )
    );
    $response->assertInertia(function (AssertableInertia $page) {
        $page
            ->component('Org/Catalogue/Shops')
            ->has('title')
            ->has('breadcrumbs', 2)
            ->has('tabs')
            ->has('shops')
            ->has('pageHead');
    });
})->depends('SetUserAuthorisedModels command');


test('UI show dashboard org (tab invoice_categories)', function (User $user) {
    $this->withoutExceptionHandling();

    actingAs($user);
    /** @var Organisation $organisation */
    $organisation = $user->authorisedOrganisations()->first();

    $response = get(
        route(
            'grp.org.dashboard.show',
            [
                $organisation->slug,
                'tab_dashboard_interval' => 'invoice_categories',
            ]
        )
    );

    $response->assertInertia(function (AssertableInertia $page) use ($organisation) {
        $page
            ->component('Dashboard/OrganisationDashboard')
            ->has('breadcrumbs', 1)
            ->has(
                'dashboard',
                fn (AssertableInertia $page) =>
                $page->has('table', 2)
                ->has('interval_options')
                ->has('settings')
                ->where('currency_code', $organisation->currency->code)
                ->etc()
            );
    });
})->depends('SetUserAuthorisedModels command')->todo();


test('UI index overview org', function (User $user) {
    $this->withoutExceptionHandling();

    actingAs($user);
    /** @var Organisation $organisation */
    $organisation = $user->authorisedOrganisations()->first();

    $response = get(
        route(
            'grp.org.overview.hub',
            [
                $organisation->slug
            ]
        )
    );

    $response->assertInertia(function (AssertableInertia $page) {
        $page
            ->component('Overview/OverviewHub')
            ->where('title', 'overview')
            ->has('breadcrumbs', 2)
            ->has(
                'pageHead',
                fn (AssertableInertia $page) => $page
                    ->where('title', 'overview')
                    ->etc()
            )->has('dashboard_stats');
    });
})->depends('SetUserAuthorisedModels command');

test('UI index overview org changelog', function (User $user) {
    $this->withoutExceptionHandling();

    actingAs($user);
    /** @var Organisation $organisation */
    $organisation = $user->authorisedOrganisations()->first();

    $response = get(
        route(
            'grp.org.overview.changelog.index',
            [
                $organisation->slug
            ]
        )
    );

    $response->assertInertia(function (AssertableInertia $page) {
        $page
            ->component('SysAdmin/Histories')
            ->where('title', 'Changelog')
            ->has('breadcrumbs', 3)
            ->has(
                'pageHead',
                fn (AssertableInertia $page) => $page
                    ->where('title', 'Changelog')
                    ->etc()
            )->has('data');
    });
})->depends('SetUserAuthorisedModels command');


test('create guest from command', function (Group $group) {
    expect($group->sysadminStats->number_guests)->toBe(1);

    $superAdminJobPosition = $group->jobPositions()->where('code', 'group-admin')->first();


    $positions = json_encode([
        [
            'slug'   => $superAdminJobPosition->slug,
            'scopes' => []
        ],
    ]);

    $this->artisan(
        'guest:create',
        [
            'group'       => $group->slug,
            'name'        => 'Mr Pika',
            'username'    => 'pika',
            '--password'  => 'hello1234',
            '--email'     => 'pika@inikoo.com',
            '--positions' => $positions
        ]
    )->assertSuccessful();

    /** @var Guest $guest */
    $guest = $group->guests()->where('code', 'pika')->firstOrFail();
    $group->refresh();
    expect($guest->getUser()->username)->toBe('pika')
        ->and($group->sysadminStats->number_guests)->toBe(2)
        ->and($group->sysadminStats->number_guests_status_active)->toBe(2)
        ->and($group->sysadminStats->number_users)->toBe(2);

    return $guest;
})->depends('create group');

test('update guest', function ($guest) {
    app()->instance('group', $guest->group);
    setPermissionsTeamId($guest->group->id);
    $guest = UpdateGuest::make()->action($guest, ['contact_name' => 'Wow']);
    expect($guest->contact_name)->toBe('Wow');

    $guest = UpdateGuest::make()->action($guest, ['contact_name' => 'John']);
    expect($guest->contact_name)->toBe('John');

    return $guest;
})->depends('create guest from command');

test('update guest credentials', function ($guest) {
    app()->instance('group', $guest->group);
    setPermissionsTeamId($guest->group->id);

    $guest = UpdateGuest::make()->action($guest, ['username' => 'test_user']);
    expect($guest->getUser()->username)->toBe('test_user')
    ->and(Hash::check('hello1234', $guest->getUser()->password))->toBeTrue();

    $guest = UpdateGuest::make()->action($guest, ['password' => 'test_user_two']);
    expect($guest->getUser()->username)->toBe('test_user')
    ->and(Hash::check('test_user_two', $guest->getUser()->password))->toBeTrue();

    return $guest;
})->depends('update guest');

test('update guest status', function ($guest) {
    app()->instance('group', $guest->group);
    setPermissionsTeamId($guest->group->id);

    $guest = UpdateGuest::make()->action($guest, ['status' => true]);
    expect($guest->getUser()->username)->toBe('test_user')
    ->and(Hash::check('test_user_two', $guest->getUser()->password))->toBeTrue()
    ->and($guest->status)->toBeTrue();

    return $guest;
})->depends('update guest credentials');

test('fail to create guest with invalid usernames', function (Group $group) {
    app()->instance('group', $group);
    setPermissionsTeamId($group->id);

    $guestData = Guest::factory()->definition();

    data_set($guestData, 'user.username', 'create');

    expect(function () use ($guestData, $group) {
        StoreGuest::make()->action(
            $group,
            $guestData
        );
    })->toThrow(ValidationException::class);

    data_set($guestData, 'user.username', 'export');
    expect(function () use ($guestData, $group) {
        StoreGuest::make()->action(
            $group,
            $guestData
        );
    })->toThrow(ValidationException::class);
})->depends('create group');


test('update user password', function (Guest $guest) {
    $user = UpdateUser::make()->action($guest->getUser(), [
        'password' => 'secret'
    ]);

    expect(Hash::check('secret', $user->password))->toBeTrue();

    return $user;
})->depends('update guest');

test('update user username', function (User $user) {
    expect($user->username)->toBe('test_user');
    $user = UpdateUser::make()->action($user, [
        'username' => 'new-username'
    ]);

    expect($user->username)->toBe('new-username')
        ->and($user->status)->toBeTrue();

    return $user;
})->depends('update user password');

test('add user roles', function (Guest $guest) {
    app()->instance('group', $guest->group);
    setPermissionsTeamId($guest->group->id);
    expect($guest->getUser()->hasRole(['supply-chain']))->toBeTrue();

    $user = UserAddRoles::make()->action($guest->getUser(), ['system-admin']);

    expect($user->hasRole(['supply-chain', 'system-admin']))->toBeTrue();
})->depends('create guest');

test('remove user roles', function (Guest $guest) {
    app()->instance('group', $guest->group);
    setPermissionsTeamId($guest->group->id);
    $user = UserRemoveRoles::make()->action($guest->getUser(), ['group-admin', 'system-admin']);

    expect($user->hasRole(['group-admin', 'system-admin']))->toBeFalse();
})->depends('create guest');

test('sync user roles', function (Guest $guest) {
    app()->instance('group', $guest->group);
    setPermissionsTeamId($guest->group->id);
    $user = UserSyncRoles::make()->action($guest->getUser(), ['group-admin', 'system-admin']);

    expect($user->hasRole(['group-admin', 'system-admin']))->toBeTrue();
})->depends('create guest');


test('user status change', function (User $user) {
    expect($user->status)->toBeTrue();
    $user = UpdateUserStatus::make()->action($user, false);
    expect($user->status)->toBeFalse();
})->depends('update user password');

test('delete guest', function (User $user) {
    $guest = DeleteGuest::make()->action($user->guests()->first());
    expect($guest->deleted_at)->toBeInstanceOf(Carbon::class);
})->depends('update user password');


test('can show app login', function () {
    Config::set(
        'inertia.testing.page_paths',
        [resource_path('js/Pages/Grp')]
    );
    $response = get(route('grp.login.show'));

    $response->assertInertia(function (AssertableInertia $page) {
        $page->component('SysAdmin/Login');
    });
});

test('can not login with wrong credentials', function (Guest $guest) {
    $response = $this->post(route('grp.login.store'), [
        'username' => $guest->getUser()->username,
        'password' => 'wrong password',
    ]);

    /** @noinspection HttpUrlsUsage */
    $response->assertRedirect('http://app.'.config('app.domain'));
    $response->assertSessionHasErrors('username');

    $user = $guest->getUser();
    $user->refresh();
    expect($user->stats->number_failed_logins)->toBe(1);
})->depends('create guest');

test('can login', function (Guest $guest) {
    /** @var User $user */
    $user = $guest->getUser();

    $response = $this->post(route('grp.login.store'), [
        'username' => $user->username,
        'password' => 'secret-password',
    ]);

    /** @var Organisation $organisation */
    $organisation = $user->authorisedOrganisations()->first();
    $response->assertRedirect(route('grp.org.dashboard.show', [
        'organisation' => $organisation->slug,
    ]));
    $this->assertAuthenticatedAs($user);

    $user->refresh();
    expect($user->stats->number_logins)->toBe(1);
})->depends('create guest');



test('Hydrate group', function (Group $group) {
    HydrateGroup::run($group);

    $this->artisan('hydrate:groups')->assertSuccessful();
})->depends('create group');

test('Hydrate organisations', function (Organisation $organisation) {
    HydrateOrganisations::run($organisation);
    $this->artisan('hydrate:organisations')->assertSuccessful();
})->depends('create organisation type shop');

test('seed stock images', function () {
    $this->artisan('group:seed-stock-images')->assertSuccessful();
});

test('hydrate media', function (Group $group) {
    /** @var Media $media */
    $media = $group->images()->first();
    HydrateMedia::run($media);
    $this->artisan('hydrate:medias')->assertSuccessful();

    $media->refresh();
    expect($media->usage)->toBe(1)
        ->and($media->multiplicity)->toBe(2);
})->depends('create group');

test('can show media', function (Guest $guest) {
    $group = $guest->group;
    app()->instance('group', $guest->group);
    setPermissionsTeamId($group->id);

    /** @var Media $media */
    $media = $group->images()->where('mime_type', 'image/png')->first();
    actingAs($guest->getUser());
    $response = get(route('grp.media.show', $media->ulid));
    $response->assertOk();
    $response->assertHeader('Content-Type', 'image/png');
})->depends('create guest');

test('hydrate address', function (Organisation $organisation) {
    $address = $organisation->address;
    HydrateAddress::run($address);
    $this->artisan('hydrate:addresses')->assertSuccessful();

})->depends('create organisation type shop');

test('parse country', function () {
    $countryId = ParseCountryID::run('malaysia');
    /** @var Country $country */
    $country   = Country::find($countryId);
    expect($country->code)->toBe('MY');

    $countryId = ParseCountryID::run('DEU');
    /** @var Country $country */
    $country   = Country::find($countryId);
    expect($country->code)->toBe('DE');
});

test('get helpers select options data', function () {
    $countryData = GetAddressData::run();
    expect($countryData)->toHaveCount(259);
    $countryDataBis = GetCountriesOptions::run();
    expect($countryDataBis)->toHaveCount(259);
    $currencyData = GetCurrenciesOptions::run();
    expect($currencyData)->toHaveCount(159);
    $timezonesData = GetTimeZonesOptions::run();
    expect(count($timezonesData))->toBeGreaterThan(400);
    $languagesData = GetLanguagesOptions::make()->all();
    expect($languagesData)->toHaveCount(279);
    $translatedLanguagesData = GetLanguagesOptions::make()->translated();
    expect($translatedLanguagesData)->toBeGreaterThan(15);
});

test('update search', function () {
    $this->artisan('search')->assertSuccessful();
});

test('show log in', function () {
    $response = $this->get(route('grp.login.show'));
    $response->assertInertia(function (AssertableInertia $page) {
        $page->component('SysAdmin/Login');
    });
});

test('should not show without authentication', function () {
    $response = $this->get(route('grp.dashboard.show'));
    $response->assertStatus(302);
    $response->assertRedirect(route('grp.login.show'));
});

test('reindex search', function () {
    $this->artisan('search')->assertSuccessful();
});

test('employee job position in another organisation', function () {
    $group = Group::where('slug', 'test')->first();
    $org1  = $group->organisations()->first();


    $org2 = StoreOrganisation::make()->action($group, Organisation::factory()->definition());
    $group->refresh();
    expect($org2)->toBeInstanceOf(Organisation::class);



    $employee = StoreEmployee::make()->action(
        $org1,
        array_merge(
            Employee::factory()->definition(),
            [
                'username' => 'username-123',
                'password' => 'password-123',
            ]
        )
    );
    $user     = $employee->getUser();

    $jobPosition1 = $org2->jobPositions()->where('code', 'hr-c')->first();

    expect($group->number_organisations)->toBe(2)
        ->and($employee)->toBeInstanceOf(Employee::class)
        ->and($user)->toBeInstanceOf(User::class)
        ->and($jobPosition1)->toBeInstanceOf(JobPosition::class);


    $user = UpdateUserOrganisationPseudoJobPositions::make()->action(
        $user,
        $org2,
        [
            'permissions' => [
                $jobPosition1->code => []
            ]
        ]
    );
    $user->refresh();


    expect($user->authorisedOrganisations()->count())->toBe(1)
        ->and($user->authorisedOrganisations()->where('model_type', 'Organisation')->where('model_id', $org2->id)->count())->toBe(1)
        ->and($user->authorisedOrganisations()->where('model_type', 'Organisation')->where('model_id', $org1->id)->count())->toBe(0);

    return $employee;
});

test('UI index users (in Employee)', function (Employee $employee) {
    $this->withoutExceptionHandling();
    $user = $employee->getUser();
    actingAs($user);

    $response = get(
        route(
            'grp.org.hr.employees.show.users.index',
            [
                'organisation' => $employee->organisation->slug,
                'employee'     => $employee->slug
            ]
        )
    );

    $response->assertInertia(function (AssertableInertia $page) {
        $page
            ->component('SysAdmin/Users')
            ->has('breadcrumbs', 3)
            ->has('title')
            ->has('data')
            ->has('pageHead');
    });

})->depends('employee job position in another organisation')->todo();//authorisation issue

test('users search', function () {
    $this->artisan('search:users')->assertExitCode(0);

    $user = User::first();
    ReindexUserSearch::run($user);
    expect($user->universalSearch()->count())->toBe(1);
});

test('can show hr dashboard', function () {

    actingAs(User::first());

    $this->withoutExceptionHandling();
    $response = get(route('grp.sysadmin.dashboard'));

    $response->assertInertia(function (AssertableInertia $page) {
        $page
        ->component('SysAdmin/SysAdminDashboard')
        ->has('breadcrumbs', 2);
    });

});


test('UI show organisation setting', function () {
    $this->withoutExceptionHandling();
    actingAs(User::first());
    $organisation = Organisation::first();

    $response = get(
        route(
            'grp.org.settings.edit',
            [
                $organisation->slug,
            ]
        )
    );
    $response->assertInertia(function (AssertableInertia $page) use ($organisation) {
        $page
            ->component('EditModel')
            ->has('title')
            ->has('breadcrumbs', 2)
            ->has('formData.blueprint.0.fields', 5)
            ->has('formData.blueprint.1.fields', 2)
            ->has('pageHead')
            ->has(
                'formData.args.updateRoute',
                fn (AssertableInertia $page) => $page
                    ->where('name', 'grp.models.org.settings.update')
                    ->where('parameters', [$organisation->id])
            );
    });
});


test('UI index organisation', function () {

    actingAs(User::first());

    $this->withoutExceptionHandling();
    $response = get(
        route(
            'grp.organisations.index',
        )
    );
    $response->assertInertia(function (AssertableInertia $page) {
        $page
            ->component('Organisations/Organisations')
            ->where('title', 'organisations')
            ->has('breadcrumbs', 2)
            ->has('data')
            ->has(
                'pageHead',
                fn (AssertableInertia $page) => $page
                    ->where('title', 'organisations')
                    ->etc()
            );
    });
});

test('UI edit organisation', function () {
    actingAs(User::first());

    $organisation = Organisation::first();

    $this->withoutExceptionHandling();
    $response = get(
        route(
            'grp.organisations.edit',
            [$organisation->slug]
        )
    );
    $response->assertInertia(function (AssertableInertia $page) use ($organisation) {
        $page
            ->component('EditModel')
            ->where('title', 'organisation')
            ->has('breadcrumbs', 3)
            ->has('formData')
            ->has(
                'pageHead',
                fn (AssertableInertia $page) => $page
                    ->where('title', $organisation->name)
                    ->etc()
            );
    });
});

test('UI organisation edit settings', function () {
    actingAs(User::first());
    $organisation = Organisation::first();

    $response = get(
        route(
            'grp.org.settings.edit',
            [$organisation->slug]
        )
    );
    $response->assertInertia(function (AssertableInertia $page) {
        $page
            ->component('EditModel')
            ->where('title', 'Organisation settings')
            ->has('breadcrumbs', 2)
            ->has('formData')
            ->has(
                'pageHead',
                fn (AssertableInertia $page) => $page
                    ->where('title', 'Organisation settings')
                    ->etc()
            );
    });
});

test('UI get section route group sysadmin index', function () {
    $organisation = Organisation::first();

    $sectionScope = GetSectionRoute::make()->handle('grp.sysadmin.dashboard', []);
    expect($sectionScope)->toBeInstanceOf(AikuScopedSection::class)
        ->and($sectionScope->code)->toBe(AikuSectionEnum::GROUP_SYSADMIN->value)
        ->and($sectionScope->model_slug)->toBe($organisation->group->slug);
});

test('UI get section route group dashboard', function () {
    $organisation = Organisation::first();

    $sectionScope = GetSectionRoute::make()->handle('grp.dashboard', []);
    expect($sectionScope)->toBeInstanceOf(AikuScopedSection::class)
        ->and($sectionScope->code)->toBe(AikuSectionEnum::GROUP_DASHBOARD->value)
        ->and($sectionScope->model_slug)->toBe($organisation->group->slug);
});

test('UI get section route group goods dashboard', function () {
    $organisation = Organisation::first();

    $sectionScope = GetSectionRoute::make()->handle('grp.goods.dashboard', []);
    expect($sectionScope)->toBeInstanceOf(AikuScopedSection::class)
        ->and($sectionScope->code)->toBe(AikuSectionEnum::GROUP_GOODS->value)
        ->and($sectionScope->model_slug)->toBe($organisation->group->slug);
});

test('UI get section route group organisation dashboard', function () {
    $organisation = Organisation::first();

    $sectionScope = GetSectionRoute::make()->handle('grp.organisations.index', []);
    expect($sectionScope)->toBeInstanceOf(AikuScopedSection::class)
        ->and($sectionScope->code)->toBe(AikuSectionEnum::GROUP_ORGANISATION->value)
        ->and($sectionScope->model_slug)->toBe($organisation->group->slug);
});

test('UI get section route group profile dashboard', function () {
    $organisation = Organisation::first();

    $sectionScope = GetSectionRoute::make()->handle('grp.profile.showcase.show', []);
    expect($sectionScope)->toBeInstanceOf(AikuScopedSection::class)
        ->and($sectionScope->code)->toBe(AikuSectionEnum::GROUP_PROFILE->value)
        ->and($sectionScope->model_slug)->toBe($organisation->group->slug);
});

test('UI get section route org dashboard', function () {
    $organisation = Organisation::first();
    $sectionScope = GetSectionRoute::make()->handle('grp.org.dashboard.show', [
        'organisation' => $organisation->slug,
    ]);

    expect($sectionScope)->toBeInstanceOf(AikuScopedSection::class)
        ->and($sectionScope->code)->toBe(AikuSectionEnum::ORG_DASHBOARD->value)
        ->and($sectionScope->model_slug)->toBe($organisation->slug);
});

test('UI get section route org setting edit', function () {
    $organisation = Organisation::first();

    $sectionScope = GetSectionRoute::make()->handle('grp.org.settings.edit', [
        'organisation' => $organisation->slug,
    ]);

    expect($sectionScope)->toBeInstanceOf(AikuScopedSection::class)
        ->and($sectionScope->code)->toBe(AikuSectionEnum::ORG_SETTINGS->value)
        ->and($sectionScope->model_slug)->toBe($organisation->slug);
});



test('UI get section route org reports index', function () {
    $organisation = Organisation::first();

    $sectionScope = GetSectionRoute::make()->handle('grp.org.reports.index', [
        'organisation' => $organisation->slug,
    ]);

    expect($sectionScope)->toBeInstanceOf(AikuScopedSection::class)
        ->and($sectionScope->code)->toBe(AikuSectionEnum::ORG_REPORT->value)
        ->and($sectionScope->model_slug)->toBe($organisation->slug);
});

test('UI get section route org shops index', function () {
    $organisation = Organisation::first();

    $sectionScope = GetSectionRoute::make()->handle('grp.org.shops.index', [
        'organisation' => $organisation->slug,
    ]);

    expect($sectionScope)->toBeInstanceOf(AikuScopedSection::class)
        ->and($sectionScope->code)->toBe(AikuSectionEnum::ORG_SHOP->value)
        ->and($sectionScope->model_slug)->toBe($organisation->slug);
});

test('UI index request logs', function () {
    $this->withoutExceptionHandling();

    actingAs(User::first());

    $response = get(
        route(
            'grp.sysadmin.analytics.request.index',
        )
    );

    $response->assertInertia(function (AssertableInertia $page) {
        $page
            ->component('SysAdmin/UserRequests')
            ->where('title', 'User Requests')
            ->has('breadcrumbs', 3)
            ->has(
                'pageHead',
                fn (AssertableInertia $page) => $page
                    ->where('title', 'User Requests')
                    ->etc()
            )->has('data');
    });
});

test('UI index overview group', function () {
    $this->withoutExceptionHandling();

    actingAs(User::first());

    $response = get(
        route(
            'grp.overview.hub',
        )
    );

    $response->assertInertia(function (AssertableInertia $page) {
        $page
            ->component('Overview/OverviewHub')
            ->where('title', 'overview')
            ->has('breadcrumbs', 2)
            ->has(
                'pageHead',
                fn (AssertableInertia $page) => $page
                    ->where('title', 'overview')
                    ->etc()
            )->has('dashboard_stats');
    });
});

test('UI index overview group changelog', function () {
    $this->withoutExceptionHandling();

    actingAs(User::first());

    $response = get(
        route(
            'grp.overview.sysadmin.changelog.index',
        )
    );

    $response->assertInertia(function (AssertableInertia $page) {
        $page
            ->component('SysAdmin/Histories')
            ->where('title', 'Changelog')
            ->has('breadcrumbs', 3)
            ->has(
                'pageHead',
                fn (AssertableInertia $page) => $page
                    ->where('title', 'Changelog')
                    ->etc()
            )->has('data');
    });
});

test('UI show dashboard group', function () {
    $this->withoutExceptionHandling();

    actingAs(User::first());

    $group = group();

    $response = get(
        route(
            'grp.dashboard.show',
        )
    );

    $response->assertInertia(function (AssertableInertia $page) use ($group) {
        $page
            ->component('Dashboard/GrpDashboard')
            ->has('breadcrumbs', 1)
            ->has(
                'dashboard',
                fn (AssertableInertia $page) =>
                $page->has('table', 2)
                ->has('interval_options')
                ->has('settings')
                ->where('currency_code', $group->currency->code)
                ->etc()
            );
    });
})->todo();

test('UI show goods dashboard group', function () {
    $this->withoutExceptionHandling();

    actingAs(User::first());

    $response = get(
        route(
            'grp.goods.dashboard',
        )
    );

    $response->assertInertia(function (AssertableInertia $page) {
        $page
            ->component('Goods/GoodsDashboard')
            ->has('breadcrumbs', 2)
            ->has('pageHead', fn (AssertableInertia $page) => $page->where('title', 'goods strategy')->etc())
            ->has('flatTreeMaps');
    });
});

test('UI show dashboard group (tab invoice_shops)', function () {
    $this->withoutExceptionHandling();

    actingAs(User::first());
    $group = group();

    $response = get(
        route(
            'grp.dashboard.show',
            [
                'tab_dashboard_interval' => 'invoice_shops',
            ]
        )
    );

    $response->assertInertia(function (AssertableInertia $page) use ($group) {
        $page
            ->component('Dashboard/GrpDashboard')
            ->has('breadcrumbs', 1)
            ->has(
                'dashboard',
                fn (AssertableInertia $page) =>
                $page->has('table', 2)
                ->has('interval_options')
                ->has('settings')
                ->where('currency_code', $group->currency->code)
                ->etc()
            );
    });
})->todo();

test('test repair admins command', function () {
    $this->artisan('users:repair_admins_auth')->assertSuccessful();
    RepairUsersAdminsAuth::run(User::first());
});

test('Hydrate users', function () {
    HydrateUser::run(User::first());
    $this->artisan('hydrate:users')->assertSuccessful();
});

test('Hydrate guests', function () {
    HydrateGuest::run(Guest::first());
    $this->artisan('hydrate:guests')->assertSuccessful();
});

test('sysadmin hydrator', function () {
    $this->artisan('hydrate -s sys')->assertExitCode(0);
});
