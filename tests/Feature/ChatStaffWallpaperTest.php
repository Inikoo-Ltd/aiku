<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 27 Nov 2026 16:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

/** @noinspection PhpUnhandledExceptionInspection */

use App\Actions\Chat\Staff\StaffChatWallpaper;
use App\Actions\Chat\Staff\StoreStaffConversation;
use App\Actions\SysAdmin\User\UI\GetLoggedUser;
use App\Http\Resources\Chat\StaffConversationResource;
use App\Models\SysAdmin\User;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Event;

use function Pest\Laravel\actingAs;

beforeAll(function () {
    loadDB();
});

beforeEach(function () {
    list($this->organisation, $this->user) = createShop();
    $this->otherUser = User::where('group_id', $this->user->group_id)->where('id', '!=', $this->user->id)->first()
        ?? User::factory()->create(['group_id' => $this->user->group_id, 'language_id' => $this->user->language_id]);
    $this->user->update(['settings' => [...($this->user->settings ?? []), 'chat_wallpaper' => null, 'chat_wallpaper_channels' => null]]);
    $this->otherUser->update(['settings' => [...($this->otherUser->settings ?? []), 'chat_wallpaper' => null]]);

    Event::fake([\App\Events\StaffMessageSent::class]);
    $this->conversation = StoreStaffConversation::run($this->user, ['user_ids' => [$this->otherUser->id]]);
});

afterEach(function () {
    Carbon::setTestNow();
});

$resourceFor = fn (User $viewer, $conversation) => (new StaffConversationResource($conversation->fresh()->load('participants')))
    ->toArray(Request::create('/')->setUserResolver(fn () => $viewer));

test('the general wallpaper is saved and loaded, and the default stays plain', function () {
    expect(StaffChatWallpaper::general($this->user))->toBe(['pattern' => null, 'colour' => null]);

    actingAs($this->user)->patchJson(route('grp.models.profile.update'), ['chat_wallpaper' => ['pattern' => 'dots', 'colour' => 'sage']])->assertSuccessful();

    $this->user->refresh();
    expect(StaffChatWallpaper::general($this->user))->toBe(['pattern' => 'dots', 'colour' => StaffChatWallpaper::COLOURS['sage']])
        ->and(GetLoggedUser::run($this->user)['settings']['chat_wallpapers']['stored'])->toBe(['pattern' => 'dots', 'colour' => 'sage', 'style' => 'rows']);

    actingAs($this->user)->patchJson(route('grp.models.profile.update'), ['chat_wallpaper' => ['style' => 'bubbles']])->assertSuccessful();
    expect(StaffChatWallpaper::stored($this->user->fresh())['style'])->toBe('bubbles')
        ->and(StaffChatWallpaper::stored($this->user->fresh())['pattern'])->toBe('dots');
});

test('an unknown wallpaper is rejected', function () {
    actingAs($this->user)->patchJson(route('grp.models.profile.update'), ['chat_wallpaper' => ['pattern' => 'flag-of-somewhere']])->assertUnprocessable();
    actingAs($this->user)->patchJson(route('grp.models.profile.update'), ['chat_wallpaper' => ['colour' => '#ff0000']])->assertUnprocessable();
    actingAs($this->user)->patchJson(route('grp.models.profile.update'), ['chat_wallpaper' => ['style' => 'cards']])->assertUnprocessable();
    actingAs($this->user)->postJson(route('grp.chat.staff.wallpaper.update'), ['conversation_ulid' => $this->conversation->ulid, 'pattern' => 'nope', 'colour' => 'none'])->assertUnprocessable();
});

test('a wallpaper for one conversation is that person\'s only and clearing it falls back to the general one', function () use ($resourceFor) {
    actingAs($this->user)->patchJson(route('grp.models.profile.update'), ['chat_wallpaper' => ['pattern' => 'waves', 'colour' => 'sky']])->assertSuccessful();

    actingAs($this->user)->postJson(route('grp.chat.staff.wallpaper.update'), ['conversation_ulid' => $this->conversation->ulid, 'pattern' => 'dots', 'colour' => 'blush'])
        ->assertSuccessful()
        ->assertJson(['wallpaper' => ['pattern' => 'dots', 'colour' => StaffChatWallpaper::COLOURS['blush']], 'is_set' => true]);

    $mine   = $resourceFor($this->user->fresh(), $this->conversation);
    $theirs = $resourceFor($this->otherUser->fresh(), $this->conversation);

    expect($mine['wallpaper'])->toBe(['pattern' => 'dots', 'colour' => StaffChatWallpaper::COLOURS['blush']])
        ->and($mine['wallpaper_is_set'])->toBeTrue()
        ->and($theirs['wallpaper'])->toBe(['pattern' => null, 'colour' => null])
        ->and($theirs['wallpaper_is_set'])->toBeFalse()
        ->and(json_encode($theirs['participants']))->not->toContain('blush');

    actingAs($this->user)->postJson(route('grp.chat.staff.wallpaper.update'), ['conversation_ulid' => $this->conversation->ulid, 'pattern' => null, 'colour' => null])
        ->assertSuccessful()
        ->assertJson(['wallpaper' => ['pattern' => 'waves', 'colour' => StaffChatWallpaper::COLOURS['sky']], 'is_set' => false]);

    $stranger     = User::factory()->create(['group_id' => $this->user->group_id, 'language_id' => $this->user->language_id]);
    $notTheirChat = StoreStaffConversation::run($this->user, ['user_ids' => [$stranger->id]]);
    actingAs($this->otherUser)->postJson(route('grp.chat.staff.wallpaper.update'), ['conversation_ulid' => $notTheirChat->ulid, 'pattern' => 'dots', 'colour' => 'none'])->assertUnprocessable();
});

test('a seasonal wallpaper is offered only in its window and gives way to the previous one when it closes', function () {
    Carbon::setTestNow('2026-07-10 10:00:00');
    actingAs($this->user)->patchJson(route('grp.models.profile.update'), ['chat_wallpaper' => ['pattern' => 'snow']])->assertUnprocessable();
    actingAs($this->user)->patchJson(route('grp.models.profile.update'), ['chat_wallpaper' => ['pattern' => 'waves']])->assertSuccessful();

    Carbon::setTestNow('2026-12-20 10:00:00');
    actingAs($this->user)->patchJson(route('grp.models.profile.update'), ['chat_wallpaper' => ['pattern' => 'snow']])->assertSuccessful();
    expect(StaffChatWallpaper::general($this->user->fresh())['pattern'])->toBe('snow')
        ->and(collect(StaffChatWallpaper::catalogue($this->user->fresh())['patterns'])->firstWhere('key', 'snow')['is_new'])->toBeTrue();

    actingAs($this->user)->postJson(route('grp.chat.staff.wallpaper.seen'))->assertSuccessful();
    expect(collect(StaffChatWallpaper::catalogue($this->user->fresh())['patterns'])->firstWhere('key', 'snow')['is_new'])->toBeFalse();

    expect(StaffChatWallpaper::general($this->user->fresh(), Carbon::parse('2027-01-03'))['pattern'])->toBe('snow')
        ->and(StaffChatWallpaper::general($this->user->fresh(), Carbon::parse('2027-01-10'))['pattern'])->toBe('waves');
});

test('the login watercolour can be chosen, and every pattern has its picture, INI-597', function () {
    actingAs($this->user)->patchJson(route('grp.models.profile.update'), ['chat_wallpaper' => ['pattern' => 'watercolour', 'colour' => 'none']])->assertSuccessful();

    expect(StaffChatWallpaper::general($this->user->fresh())['pattern'])->toBe('watercolour');
    foreach (array_keys(StaffChatWallpaper::PATTERNS) as $pattern) {
        expect(file_exists(resource_path("js/Components/Messaging/wallpapers/$pattern.svg")))->toBeTrue();
    }
});

test('Verba look and feel holds the sidebar colour, wallpaper and the more room switch, which starts on and is saved when turned off, INI-598 INI-599', function () {
    $this->user->update(['settings' => [...($this->user->settings ?? []), 'chat_theme' => 'nord', 'verba_more_room' => null]]);

    $verba = collect(\App\Actions\UI\Profile\EditProfileSettings::make()->generateBlueprint($this->user->fresh())['formData']['blueprint'])->firstWhere('key', 'verba');
    expect($verba['fields']['chat_wallpaper']['sidebar_colour'])->toBe('nord')
        ->and($verba['fields']['verba_more_room']['value'])->toBeTrue()
        ->and(GetLoggedUser::run($this->user->fresh())['settings']['verba_more_room'])->toBeTrue();

    actingAs($this->user)->patchJson(route('grp.models.profile.update'), ['verba_more_room' => false])->assertSuccessful();
    expect(GetLoggedUser::run($this->user->fresh())['settings']['verba_more_room'])->toBeFalse();

    actingAs($this->user)->patchJson(route('grp.models.profile.update'), ['verba_more_room' => 'sometimes'])->assertUnprocessable();
});
