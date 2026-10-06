<?php

/*
 * Author Louis Perez
 * Created on 18-09-2026
 * GitHub: https://github.com/louis-perez
 * Copyright 2026
*/

/** @noinspection PhpUnhandledExceptionInspection */

use App\Enums\UI\SysAdmin\ProfileTabsEnum;
use Illuminate\Support\Facades\DB;

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

test('profile tabs no longer list clocking', function () {
    expect(ProfileTabsEnum::navigation())
        ->not->toHaveKey('clocking')
        ->not->toHaveKey('dashboard')
        ->toHaveKeys(['notifications', 'timesheets']);
});
