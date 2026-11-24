<?php

/*
 * Author Louis Perez
 * Created on 18-09-2026
 * GitHub: https://github.com/louis-perez
 * Copyright 2026
*/

/** @noinspection PhpUnhandledExceptionInspection */

use App\Actions\UI\Grp\GetFirstLoadProps;
use App\Enums\UI\SysAdmin\ProfileTabsEnum;
use App\Models\SysAdmin\User;
use App\Notifications\WorkNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;
use function Pest\Laravel\getJson;
use function Pest\Laravel\patchJson;

beforeAll(function () {
    loadDB();
});

beforeEach(function () {
    $this->group      = createGroup();
    $this->adminGuest = createAdminGuest($this->group);
    actingAs($this->adminGuest->getUser());
});

test('profile showcase exposes the contact name', function () {
    $user = $this->adminGuest->getUser();

    getJson(route('grp.profile.showcase.show'))
        ->assertOk()
        ->assertJsonPath('data.username', $user->username)
        ->assertJsonPath('data.contact_name', $user->contact_name)
        ->assertJsonMissingPath('data.email');
});

test('a nickname saved from the profile card is returned by the showcase', function () {
    patchJson(route('grp.models.profile.update'), ['nickname' => 'Card Nick'])->assertSuccessful();

    getJson(route('grp.profile.showcase.show'))
        ->assertOk()
        ->assertJsonPath('data.nickname', 'Card Nick');

    patchJson(route('grp.models.profile.update'), ['nickname' => 'x'])
        ->assertStatus(422)
        ->assertJsonValidationErrors('nickname');
});

test('profile history is paginated 10 per page whatever is asked for', function () {
    $user = $this->adminGuest->getUser();
    DB::table('audits')->insert(collect(range(1, 12))->map(fn (int $index) => [
        'auditable_type' => 'User',
        'auditable_id'   => $user->id,
        'event'          => 'updated',
        'tags'           => '[]',
        'old_values'     => json_encode(['nickname' => "Before $index"]),
        'new_values'     => json_encode(['nickname' => "After $index"]),
        'created_at'     => now(),
        'updated_at'     => now(),
    ])->all());

    $firstPage = getJson(route('grp.profile.history.index', ['history_perPage' => 50]))
        ->assertOk()
        ->assertJsonPath('meta.per_page', 10)
        ->assertJsonPath('meta.current_page', 1);
    expect($firstPage->json('data'))->toHaveCount(10)
        ->and($firstPage->json('meta.total'))->toBeGreaterThanOrEqual(12);

    $secondPage = getJson(route('grp.profile.history.index', ['historyPage' => 2]))
        ->assertOk()
        ->assertJsonPath('meta.current_page', 2);
    expect($secondPage->json('data'))->not->toBeEmpty()
        ->and(collect($secondPage->json('data'))->pluck('id')->intersect(collect($firstPage->json('data'))->pluck('id')))->toBeEmpty();
});

test('personal settings page has breadcrumbs back to the profile', function () {
    $breadcrumbs = collect(get(route('grp.profile.edit'))->assertOk()->inertiaProps()['breadcrumbs']);

    expect($breadcrumbs->pluck('simple.route.name')->filter()->values()->all())
        ->toContain('grp.profile.show')
        ->and($breadcrumbs->last()['simple']['route']['name'])->toBe('grp.profile.edit')
        ->and($breadcrumbs->last()['simple']['label'])->toBe('Personal settings');
});

test('profile tabs no longer list clocking or notifications', function () {
    expect(ProfileTabsEnum::navigation())
        ->not->toHaveKey('clocking')
        ->not->toHaveKey('dashboard')
        ->not->toHaveKey('notifications')
        ->toHaveKey('timesheets');

    getJson(route('grp.profile.page-head-tabs.show'))
        ->assertOk()
        ->assertJsonMissingPath('tabs.navigation.notifications');
});

function insertProfileNotification(User $user, string $type, string $title, string $body, ?string $readAt = null, ?string $createdAt = null): string
{
    $id = (string) Str::uuid();
    DB::table('notifications')->insert([
        'id'              => $id,
        'type'            => $type,
        'notifiable_type' => 'User',
        'notifiable_id'   => $user->id,
        'data'            => json_encode(['title' => $title, 'body' => $body]),
        'read_at'         => $readAt,
        'created_at'      => $createdAt ?? now(),
        'updated_at'      => $createdAt ?? now(),
    ]);

    return $id;
}

test('the all notifications list searches, filters by type and state, newest first, on the server', function () {
    $user   = $this->adminGuest->getUser();
    $marker = 'NotifSearch'.Str::random(8);

    $oldTicket   = insertProfileNotification($user, 'App\Notifications\TicketNotification', "Old ticket $marker", 'first', null, now()->subDays(3)->toDateTimeString());
    $newTicket   = insertProfileNotification($user, 'App\Notifications\TicketNotification', 'New ticket', "about $marker", null, now()->subDay()->toDateTimeString());
    $readArrival = insertProfileNotification($user, 'App\Notifications\ShopStockArrivalsNotification', "Back in stock $marker", 'stock', now()->toDateTimeString(), now()->subDays(2)->toDateTimeString());
    insertProfileNotification($user, WorkNotification::class, "Work $marker", 'mobile only');

    $search = getJson(route('grp.profile.notifications.index', ['filter' => ['search' => strtolower($marker)]]))->assertOk();
    expect(collect($search->json('data'))->pluck('id')->all())->toBe([$newTicket, $readArrival, $oldTicket])
        ->and($search->json('meta.total'))->toBe(3);

    $tickets = getJson(route('grp.profile.notifications.index', ['filter' => ['search' => $marker, 'type' => 'App\Notifications\TicketNotification']]))->assertOk();
    expect(collect($tickets->json('data'))->pluck('id')->all())->toBe([$newTicket, $oldTicket]);

    $unread = getJson(route('grp.profile.notifications.index', ['filter' => ['search' => $marker, 'state' => 'unread']]))->assertOk();
    expect(collect($unread->json('data'))->pluck('id')->all())->toBe([$newTicket, $oldTicket]);

    $read = getJson(route('grp.profile.notifications.index', ['filter' => ['search' => $marker, 'state' => 'read']]))->assertOk();
    expect(collect($read->json('data'))->pluck('id')->all())->toBe([$readArrival]);

    getJson(route('grp.profile.notifications.index', ['filter' => ['search' => $marker], 'notifications_perPage' => 50, 'notificationsPage' => 2]))
        ->assertOk()
        ->assertJsonPath('meta.per_page', 50)
        ->assertJsonPath('meta.current_page', 2)
        ->assertJsonCount(0, 'data');
});

test('notification types are listed per user with readable names and counts', function () {
    $user = $this->adminGuest->getUser();

    $countOf = fn (string $type) => collect(getJson(route('grp.profile.notifications.types'))->assertOk()->json())->firstWhere('value', $type)['count'] ?? 0;
    $ticketsBefore = $countOf('App\Notifications\TicketNotification');

    insertProfileNotification($user, 'App\Notifications\TicketNotification', 'Ticket', 'one');
    insertProfileNotification($user, 'App\Notifications\TicketNotification', 'Ticket', 'two');
    insertProfileNotification($user, 'App\Notifications\SupplierCatalogueRefreshedNotification', 'Catalogue', 'refreshed');
    insertProfileNotification($user, WorkNotification::class, 'Work', 'mobile only');

    $types = collect(getJson(route('grp.profile.notifications.types'))->assertOk()->json());

    expect($types->firstWhere('value', 'App\Notifications\TicketNotification'))
        ->toMatchArray(['label' => 'Tickets', 'group' => 'work', 'group_label' => 'Work', 'count' => $ticketsBefore + 2])
        ->and($types->firstWhere('value', 'App\Notifications\SupplierCatalogueRefreshedNotification'))
        ->toMatchArray(['label' => 'Supplier catalogue refreshed', 'group' => 'other'])
        ->and($types->pluck('value'))->not->toContain(WorkNotification::class)
        ->and($types->pluck('group')->unique()->values()->first())->toBe('work');
});

test('the notifications rail badge can be hidden like the others', function () {
    patchJson(route('grp.models.profile.update'), ['rail_hidden_badges' => ['notifications', 'tickets_queue']])->assertSuccessful();

    expect($this->adminGuest->getUser()->fresh()->settings['rail_hidden_badges'])->toBe(['notifications', 'tickets_queue']);

    patchJson(route('grp.models.profile.update'), ['rail_hidden_badges' => []])->assertSuccessful();
});

test('the rail gets the latest 50 notifications', function () {
    $user = $this->adminGuest->getUser();
    collect(range(1, 52))->each(fn (int $index) => insertProfileNotification($user, 'App\Notifications\TicketNotification', "Rail $index", 'body', null, now()->addMinutes($index)->toDateTimeString()));

    $notifications = GetFirstLoadProps::make()->badges($user)['notifications'];

    expect($notifications)->toHaveCount(50)
        ->and($notifications->first()->resolve()['title'])->toBe('Rail 52');
});
