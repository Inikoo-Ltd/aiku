<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 21 Nov 2026 21:30:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

/** @noinspection PhpUnhandledExceptionInspection */

use App\Actions\Chat\Staff\Slack\StoreSlackMessage;
use App\Actions\Chat\Staff\Slack\StoreSlackMessageReaction;
use App\Events\SlackChannelMessageChanged;
use App\Models\Chat\SlackChannel;
use App\Models\Chat\SlackConnection;
use App\Models\Chat\SlackMessage;
use App\Models\Chat\SlackMessageReaction;
use App\Models\Chat\SlackUser;
use App\Models\SysAdmin\User;
use App\Models\Web\Website;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

use function Pest\Laravel\actingAs;

beforeAll(function () {
    loadDB();
});

beforeEach(function () {
    $web = Website::has('shop')->first();
    if (!$web) {
        list($this->organisation, $this->user) = createShop();
    } else {
        $this->organisation = $web->organisation;
        $this->user         = createAdminGuest($this->organisation->group)->getUser();
    }
    $this->group = $this->organisation->group;
    app()->instance('group', $this->group);
    setPermissionsTeamId($this->group->id);
    Event::fake([SlackChannelMessageChanged::class]);
    Http::preventStrayRequests();

    $this->s  = Str::upper(Str::random(6));
    $this->me = User::factory()->create(['group_id' => $this->group->id, 'language_id' => $this->user->language_id]);
    $this->me->assignRole('help-desk-clerk');
    $this->mySlackId = "UME{$this->s}";
    SlackUser::create(['slack_id' => $this->mySlackId, 'user_id' => $this->me->id, 'email' => $this->me->email, 'real_name' => 'Me Myself']);
    SlackUser::create(['slack_id' => "UOTHER{$this->s}", 'real_name' => 'Other Person']);
    $this->connect = fn (User $user, string $slackId) => SlackConnection::create(['user_id' => $user->id, 'slack_user_id' => $slackId, 'team_id' => 'TTEST', 'token' => 'xoxp-test', 'scopes' => implode(',', \App\Actions\Chat\Staff\Slack\Write\SlackWriteAccess::USER_SCOPES), 'connected_at' => now()]);
    ($this->connect)($this->me, $this->mySlackId);

    $this->channel = SlackChannel::create(['group_id' => $this->group->id, 'slack_id' => "CROW{$this->s}", 'name' => "row-{$this->s}", 'is_member' => true]);
    $this->ignored = SlackChannel::create(['group_id' => $this->group->id, 'slack_id' => "CROWIGN{$this->s}", 'name' => "ignored-{$this->s}", 'is_ignored' => true]);

    $start      = now()->subHour()->startOfMinute();
    $this->ts   = fn (int $i) => sprintf('%d.%06d', $start->timestamp + $i * 60, $i);
    $this->post = fn (int $i, array $extra = [], ?SlackChannel $channel = null) => StoreSlackMessage::make()->store($channel ?? $this->channel, array_merge([
        'type' => 'message',
        'user' => $this->mySlackId,
        'text' => "Message $i",
        'ts'   => ($this->ts)($i),
    ], $extra));
    $this->url = fn (string $name, ?SlackChannel $channel = null) => route($name, ['slackChannel' => ($channel ?? $this->channel)->id]);
});

test('a reaction added and removed from aiku is stored once, also after the echo event, and already_reacted counts as success, INI-459', function () {
    $message = ($this->post)(1, ['user' => "UOTHER{$this->s}"]);
    Http::fake([
        'slack.com/api/reactions.add'    => Http::sequence()->push(['ok' => true])->push(['ok' => false, 'error' => 'already_reacted']),
        'slack.com/api/reactions.remove' => Http::sequence()->push(['ok' => true])->push(['ok' => false, 'error' => 'no_reaction']),
    ]);
    $toggle = fn (array $body) => actingAs($this->me)->postJson(($this->url)('grp.chat.staff.slack.reactions.toggle'), ['ts' => $message->ts] + $body);
    $mine   = fn () => SlackMessageReaction::where('slack_message_id', $message->id)->where('slack_user_id', $this->mySlackId)->pluck('name')->all();

    $toggle(['emoji' => '👍', 'add' => true])->assertOk()->assertJsonPath('message.my_reactions', ['+1']);
    StoreSlackMessageReaction::make()->handle(['type' => 'reaction_added', 'user' => $this->mySlackId, 'reaction' => '+1', 'item' => ['type' => 'message', 'channel' => $this->channel->slack_id, 'ts' => $message->ts], 'event_ts' => ($this->ts)(2)]);
    expect($mine())->toBe(['+1']);

    $toggle(['name' => '+1', 'add' => true])->assertOk();
    expect($mine())->toBe(['+1']);

    $toggle(['name' => '+1', 'add' => false])->assertOk()->assertJsonPath('message.my_reactions', []);
    StoreSlackMessageReaction::make()->handle(['type' => 'reaction_removed', 'user' => $this->mySlackId, 'reaction' => '+1', 'item' => ['type' => 'message', 'channel' => $this->channel->slack_id, 'ts' => $message->ts], 'event_ts' => ($this->ts)(3)]);
    expect($mine())->toBe([]);

    $toggle(['name' => '+1', 'add' => false])->assertOk();
    expect($mine())->toBe([]);

    Http::assertSent(fn (Request $request) => $request->url() === 'https://slack.com/api/reactions.add'
        && $request['name'] === '+1' && $request['channel'] === $this->channel->slack_id && $request['timestamp'] === $message->ts
        && $request->hasHeader('Authorization', 'Bearer xoxp-test'));
    Http::assertSentCount(4);
});

test('editing your own message stores the new text, keeps the earlier one as a version and the echo changes nothing, INI-458', function () {
    $message = ($this->post)(1, ['text' => 'Helo & bye <@UOTHER'.$this->s.'>']);
    Http::fake(['slack.com/api/chat.update' => fn (Request $request) => Http::response(['ok' => true, 'channel' => $this->channel->slack_id, 'ts' => $message->ts, 'text' => $request['text'], 'message' => ['text' => $request['text'], 'user' => $this->mySlackId]])]);

    actingAs($this->me)->patchJson(($this->url)('grp.chat.staff.slack.messages.update'), ['ts' => $message->ts, 'text' => 'Hello & bye <@UOTHER'.$this->s.'>'])
        ->assertOk()
        ->assertJsonPath('message.is_edited', true);

    Http::assertSent(fn (Request $request) => $request['text'] === 'Hello &amp; bye <@UOTHER'.$this->s.'>');
    $message->refresh();
    expect($message->text)->toBe('Hello &amp; bye <@UOTHER'.$this->s.'>')
        ->and($message->edited_at)->not->toBeNull()
        ->and($message->versions()->pluck('text')->all())->toBe(['Helo & bye <@UOTHER'.$this->s.'>']);

    StoreSlackMessage::make()->handle(['type' => 'message', 'subtype' => 'message_changed', 'channel' => $this->channel->slack_id, 'channel_type' => 'channel', 'message' => ['type' => 'message', 'user' => $this->mySlackId, 'text' => 'Hello &amp; bye <@UOTHER'.$this->s.'>', 'ts' => $message->ts, 'edited' => ['user' => $this->mySlackId, 'ts' => sprintf('%.6f', microtime(true))], 'attachments' => [['title' => 'Preview', 'from_url' => 'https://example.com']]]]);
    expect($message->versions()->count())->toBe(1)
        ->and($message->refresh()->data['attachments'][0]['title'] ?? null)->toBe('Preview')
        ->and($message->text)->toBe('Hello &amp; bye <@UOTHER'.$this->s.'>');
});

test('deleting your own message soft deletes it and the echo changes nothing, INI-458', function () {
    $message = ($this->post)(1);
    Http::fake(['slack.com/api/chat.delete' => Http::response(['ok' => true, 'channel' => $this->channel->slack_id, 'ts' => $message->ts])]);

    actingAs($this->me)->deleteJson(($this->url)('grp.chat.staff.slack.messages.delete'), ['ts' => $message->ts])
        ->assertOk()
        ->assertJsonPath('message.is_deleted', true);
    StoreSlackMessage::make()->handle(['type' => 'message', 'subtype' => 'message_deleted', 'channel' => $this->channel->slack_id, 'channel_type' => 'channel', 'deleted_ts' => $message->ts]);

    expect(SlackMessage::find($message->id))->toBeNull()
        ->and(SlackMessage::withTrashed()->find($message->id)->trashed())->toBeTrue();
});

test('editing or deleting someone else\'s message, a bot message or a message of a channel you cannot see is refused before Slack is called, INI-458', function () {
    Http::fake();
    $others  = ($this->post)(1, ['user' => "UOTHER{$this->s}"]);
    $bot     = ($this->post)(2, ['bot_id' => 'BBOT', 'subtype' => 'bot_message']);
    $system  = ($this->post)(3, ['subtype' => 'channel_join']);
    $ignored = ($this->post)(4, [], $this->ignored);
    $edit    = fn (SlackMessage $message, ?SlackChannel $channel = null, ?User $as = null) => actingAs($as ?? $this->me)->patchJson(($this->url)('grp.chat.staff.slack.messages.update', $channel), ['ts' => $message->ts, 'text' => 'Changed']);
    $delete  = fn (SlackMessage $message, ?SlackChannel $channel = null) => actingAs($this->me)->deleteJson(($this->url)('grp.chat.staff.slack.messages.delete', $channel), ['ts' => $message->ts]);

    foreach ([$others, $bot, $system] as $message) {
        $edit($message)->assertUnprocessable()->assertJsonValidationErrors('slack');
        $delete($message)->assertUnprocessable()->assertJsonValidationErrors('slack');
    }
    $edit($ignored, $this->ignored)->assertForbidden();
    $delete($ignored, $this->ignored)->assertForbidden();

    $clerkless = User::factory()->create(['group_id' => $this->group->id, 'language_id' => $this->user->language_id]);
    $own       = ($this->post)(5);
    $edit($own, null, $clerkless)->assertForbidden();
    actingAs($clerkless)->postJson(($this->url)('grp.chat.staff.slack.reactions.toggle'), ['ts' => $own->ts, 'name' => 'tada', 'add' => true])->assertForbidden();

    $unconnected = User::factory()->create(['group_id' => $this->group->id, 'language_id' => $this->user->language_id]);
    $unconnected->assignRole('help-desk-clerk');
    actingAs($unconnected)->postJson(($this->url)('grp.chat.staff.slack.reactions.toggle'), ['ts' => $own->ts, 'name' => 'tada', 'add' => true])
        ->assertUnprocessable()
        ->assertJsonPath('errors.slack.0', __('Connect your Slack account to write here'));

    Http::assertNothingSent();
    expect($others->refresh()->text)->toBe('Message 1')
        ->and($bot->refresh()->trashed())->toBeFalse()
        ->and($ignored->refresh()->text)->toBe('Message 4');
});

test('each Slack error is shown as a sentence and leaves the stored message unchanged, INI-458 INI-459', function (string $error, string $sentence) {
    $message = ($this->post)(1);
    Http::fake(['slack.com/api/*' => Http::response(['ok' => false, 'error' => $error])]);
    $renew = fn () => SlackConnection::where('user_id', $this->me->id)->update(['needs_reconnect' => false]);

    actingAs($this->me)->patchJson(($this->url)('grp.chat.staff.slack.messages.update'), ['ts' => $message->ts, 'text' => 'Changed'])
        ->assertUnprocessable()->assertJsonPath('errors.slack.0', __($sentence));
    $renew();
    if ($error !== 'message_not_found') {
        actingAs($this->me)->deleteJson(($this->url)('grp.chat.staff.slack.messages.delete'), ['ts' => $message->ts])
            ->assertUnprocessable()->assertJsonPath('errors.slack.0', __($sentence));
    }
    $renew();
    actingAs($this->me)->postJson(($this->url)('grp.chat.staff.slack.reactions.toggle'), ['ts' => $message->ts, 'name' => 'tada', 'add' => true])
        ->assertUnprocessable();

    $message->refresh();
    expect($message->text)->toBe('Message 1')
        ->and($message->edited_at)->toBeNull()
        ->and($message->trashed())->toBeFalse()
        ->and($message->versions()->count())->toBe(0)
        ->and(SlackMessageReaction::where('slack_message_id', $message->id)->count())->toBe(0);
})->with([
    ['cant_update_message', 'Slack does not let you edit this message'],
    ['edit_window_closed', 'The time allowed to change this message in Slack has passed'],
    ['message_not_found', 'This message is no longer in Slack'],
    ['cant_delete_message', 'Slack does not let you delete this message'],
    ['token_revoked', 'Your Slack connection needs renewing. Connect again and try once more'],
    ['ratelimited', 'Slack is busy. Try again in a minute'],
]);

test('an edit keeps only the markup the message already had: anything new is escaped, entities and existing links stay as they are', function (string $original, string $edited, string $sent) {
    expect(\App\Actions\Chat\Staff\Slack\Write\EditSlackMessage::toSlackText($edited, $original))->toBe($sent);
})->with([
    'a literal broadcast stays literal'      => ['say &lt;!channel&gt; and &lt;@UX1&gt;', 'says &lt;!channel&gt; and &lt;@UX1&gt;', 'says &lt;!channel&gt; and &lt;@UX1&gt;'],
    'a new broadcast is escaped'             => ['x', '<!channel> x', '&lt;!channel&gt; x'],
    'a new user group mention is escaped'    => ['x', '<!subteam^S1> x', '&lt;!subteam^S1&gt; x'],
    'a new mention is escaped'               => ['x', '<@UNEW> x', '&lt;@UNEW&gt; x'],
    'a new labelled link is escaped'         => ['x', '<https://x.test|label> x', '&lt;https://x.test|label&gt; x'],
    'existing mention, link and & stay'      => ['hi <@UX1> see <https://a.test|a> &amp; co', 'hello <@UX1> see <https://a.test|a> &amp; co', 'hello <@UX1> see <https://a.test|a> &amp; co'],
    'a lone & and < are escaped'             => ['x', 'a & b < c', 'a &amp; b &lt; c'],
    'a channel link without its label stays' => ['see <#CSECRET|secret-name>', 'see <#CSECRET> now', 'see <#CSECRET> now'],
]);

test('an edit that adds a broadcast mention sends it escaped and notifies no one, INI-458', function () {
    $message = ($this->post)(1, ['text' => 'about &lt;!here&gt; tomorrow']);
    Http::fake(['slack.com/api/chat.update' => fn (Request $request) => Http::response(['ok' => true, 'ts' => $message->ts, 'text' => $request['text'], 'message' => ['text' => $request['text'], 'user' => $this->mySlackId]])]);

    actingAs($this->me)->patchJson(($this->url)('grp.chat.staff.slack.messages.update'), ['ts' => $message->ts, 'text' => '<!channel> about &lt;!here&gt; today'])->assertOk();

    Http::assertSent(fn (Request $request) => $request['text'] === '&lt;!channel&gt; about &lt;!here&gt; today');
    expect($message->refresh()->text)->toBe('&lt;!channel&gt; about &lt;!here&gt; today');
});

test('a message sent from aiku, which Slack returns with the app bot_id beside the person, is still the person\'s: shown as theirs, editable and deletable, INI-458', function () {
    $message = ($this->post)(1, ['bot_id' => 'BAIKU', 'app_id' => 'AAIKU', 'bot_profile' => ['id' => 'BAIKU', 'name' => 'aiku', 'app_id' => 'AAIKU'], 'team' => 'TTEST', 'blocks' => [['type' => 'rich_text', 'elements' => []]]]);
    $row     = \App\Actions\Chat\Staff\Slack\GetSlackChannelMessages::make()->rows($this->channel, SlackMessage::whereKey($message->id)->with(\App\Actions\Chat\Staff\Slack\GetSlackChannelMessages::relations())->get(\App\Actions\Chat\Staff\Slack\GetSlackChannelMessages::COLUMNS), $this->me)[0];
    expect($row['author']['is_bot'])->toBeFalse()->and($row['author']['user_id'])->toBe($this->me->id);

    Http::fake([
        'slack.com/api/chat.update' => fn (Request $request) => Http::response(['ok' => true, 'ts' => $message->ts, 'text' => $request['text'], 'message' => ['text' => $request['text'], 'user' => $this->mySlackId, 'bot_id' => 'BAIKU', 'app_id' => 'AAIKU']]),
        'slack.com/api/chat.delete' => Http::response(['ok' => true, 'ts' => $message->ts]),
    ]);
    actingAs($this->me)->patchJson(($this->url)('grp.chat.staff.slack.messages.update'), ['ts' => $message->ts, 'text' => 'Changed'])->assertOk()->assertJsonPath('message.author.is_bot', false);
    actingAs($this->me)->deleteJson(($this->url)('grp.chat.staff.slack.messages.delete'), ['ts' => $message->ts])->assertOk();

    expect(SlackMessage::withTrashed()->find($message->id)->trashed())->toBeTrue();
});

test('the raw text reaches only its author: never another person\'s row nor the broadcast, and without channel names, INI-458', function () {
    $other    = User::factory()->create(['group_id' => $this->group->id, 'language_id' => $this->user->language_id]);
    $message  = ($this->post)(1, ['text' => 'see <#CSECRET|secret-name> please']);
    $rowsFor  = fn (User $viewer) => \App\Actions\Chat\Staff\Slack\GetSlackChannelMessages::make()->rows($this->channel, SlackMessage::whereKey($message->id)->with(\App\Actions\Chat\Staff\Slack\GetSlackChannelMessages::relations())->get(\App\Actions\Chat\Staff\Slack\GetSlackChannelMessages::COLUMNS), $viewer)[0];

    expect($rowsFor($this->me)['text'])->toBe('see <#CSECRET> please')
        ->and($rowsFor($other))->not->toHaveKey('text');

    actingAs($this->me);
    $payload = (new SlackChannelMessageChanged($this->channel, $message, 'changed'))->broadcastWith();
    expect($payload['message'])->not->toHaveKey('text');

    actingAs($this->me)->getJson(($this->url)('grp.chat.staff.slack.messages.own-text').'?ts='.$message->ts)->assertOk()->assertJsonPath('text', 'see <#CSECRET> please');
    $theirs = ($this->post)(2, ['user' => "UOTHER{$this->s}"]);
    actingAs($this->me)->getJson(($this->url)('grp.chat.staff.slack.messages.own-text').'?ts='.$theirs->ts)->assertUnprocessable();
});

test('a message already gone in Slack is removed in aiku too, INI-458', function () {
    $message = ($this->post)(1);
    Http::fake(['slack.com/api/chat.delete' => Http::response(['ok' => false, 'error' => 'message_not_found'])]);

    actingAs($this->me)->deleteJson(($this->url)('grp.chat.staff.slack.messages.delete'), ['ts' => $message->ts])->assertOk()->assertJsonPath('message.is_deleted', true);
    expect(SlackMessage::withTrashed()->find($message->id)->trashed())->toBeTrue();
});

test('an edit with a link keeps Slack\'s wording, so the echo with the wrapped link makes no second version, INI-458', function () {
    $message = ($this->post)(1, ['text' => 'see the site']);
    Http::fake(['slack.com/api/chat.update' => fn (Request $request) => Http::response(['ok' => true, 'ts' => $message->ts, 'text' => $request['text'], 'message' => ['text' => 'see <https://x.test>', 'user' => $this->mySlackId]])]);

    actingAs($this->me)->patchJson(($this->url)('grp.chat.staff.slack.messages.update'), ['ts' => $message->ts, 'text' => 'see https://x.test'])->assertOk();
    StoreSlackMessage::make()->handle(['type' => 'message', 'subtype' => 'message_changed', 'channel' => $this->channel->slack_id, 'channel_type' => 'channel', 'message' => ['type' => 'message', 'user' => $this->mySlackId, 'text' => 'see <https://x.test>', 'ts' => $message->ts, 'edited' => ['user' => $this->mySlackId, 'ts' => sprintf('%.6f', microtime(true))]]]);

    expect($message->refresh()->text)->toBe('see <https://x.test>')
        ->and($message->versions()->pluck('text')->all())->toBe(['see the site']);
});

test('a picked emoji goes to Slack by its canonical name, so the echo leaves one reaction, INI-459', function (string $emoji, string $canonical) {
    $message = ($this->post)(1, ['user' => "UOTHER{$this->s}"]);
    Http::fake(['slack.com/api/reactions.add' => Http::response(['ok' => true])]);

    actingAs($this->me)->postJson(($this->url)('grp.chat.staff.slack.reactions.toggle'), ['ts' => $message->ts, 'emoji' => $emoji, 'add' => true])->assertOk();
    StoreSlackMessageReaction::make()->handle(['type' => 'reaction_added', 'user' => $this->mySlackId, 'reaction' => $canonical, 'item' => ['type' => 'message', 'channel' => $this->channel->slack_id, 'ts' => $message->ts], 'event_ts' => ($this->ts)(2)]);

    Http::assertSent(fn (Request $request) => $request['name'] === $canonical);
    expect(SlackMessageReaction::where('slack_message_id', $message->id)->pluck('name')->all())->toBe([$canonical]);
})->with([
    ['💩', 'hankey'],
    ['✋', 'hand'],
    ['👍', '+1'],
]);

test('edit, delete and react are refused on archived, private, deleted-in-Slack channels and for a ts of another channel, also through run(), before Slack is called, INI-458 INI-459', function () {
    Http::fake();
    $make = fn (array $attributes) => SlackChannel::create(['group_id' => $this->group->id, 'slack_id' => 'C'.Str::upper(Str::random(10)), 'name' => 'x-'.Str::random(6), 'is_member' => true] + $attributes);
    $channels = [
        'archived'         => $make(['is_archived' => true]),
        'private'          => $make(['is_public' => false]),
        'deleted in Slack' => $make(['deleted_in_slack_at' => now()]),
    ];

    foreach ($channels as $channel) {
        $message = SlackMessage::create(['slack_channel_id' => $channel->id, 'ts' => ($this->ts)(1), 'slack_user_id' => $this->mySlackId, 'text' => 'Mine', 'sent_at' => now(), 'data' => ['user' => $this->mySlackId, 'text' => 'Mine', 'ts' => ($this->ts)(1)]]);
        actingAs($this->me)->patchJson(($this->url)('grp.chat.staff.slack.messages.update', $channel), ['ts' => $message->ts, 'text' => 'Changed'])->assertForbidden();
        actingAs($this->me)->deleteJson(($this->url)('grp.chat.staff.slack.messages.delete', $channel), ['ts' => $message->ts])->assertForbidden();
        actingAs($this->me)->postJson(($this->url)('grp.chat.staff.slack.reactions.toggle', $channel), ['ts' => $message->ts, 'name' => 'tada', 'add' => true])->assertForbidden();
        expect(fn () => \App\Actions\Chat\Staff\Slack\Write\EditSlackMessage::run($this->me, $channel, $message->ts, 'Changed'))->toThrow(\Illuminate\Validation\ValidationException::class)
            ->and(fn () => \App\Actions\Chat\Staff\Slack\Write\DeleteSlackMessage::run($this->me, $channel, $message->ts))->toThrow(\Illuminate\Validation\ValidationException::class)
            ->and(fn () => \App\Actions\Chat\Staff\Slack\Write\ToggleSlackMessageReaction::run($this->me, $channel, $message->ts, 'tada', true))->toThrow(\Illuminate\Validation\ValidationException::class);
    }

    $elsewhere = ($this->post)(7, [], $make([]));
    actingAs($this->me)->patchJson(($this->url)('grp.chat.staff.slack.messages.update'), ['ts' => $elsewhere->ts, 'text' => 'Changed'])->assertNotFound();
    actingAs($this->me)->deleteJson(($this->url)('grp.chat.staff.slack.messages.delete'), ['ts' => $elsewhere->ts])->assertNotFound();
    actingAs($this->me)->postJson(($this->url)('grp.chat.staff.slack.reactions.toggle'), ['ts' => $elsewhere->ts, 'name' => 'tada', 'add' => true])->assertNotFound();

    Http::assertNothingSent();
});

test('the echo event arriving before Slack\'s HTTP answer leaves one version and one deletion, and each action broadcasts its kind, INI-458 INI-459', function () {
    $edited  = ($this->post)(1);
    $deleted = ($this->post)(2);
    $reacted = ($this->post)(3, ['user' => "UOTHER{$this->s}"]);
    Http::fake([
        'slack.com/api/chat.update' => function (Request $request) use ($edited) {
            $editTs = sprintf('%.6f', microtime(true));
            StoreSlackMessage::make()->handle(['type' => 'message', 'subtype' => 'message_changed', 'channel' => $this->channel->slack_id, 'channel_type' => 'channel', 'message' => ['type' => 'message', 'user' => $this->mySlackId, 'text' => $request['text'], 'ts' => $edited->ts, 'edited' => ['user' => $this->mySlackId, 'ts' => $editTs]]]);

            return Http::response(['ok' => true, 'ts' => $edited->ts, 'text' => $request['text'], 'message' => ['text' => $request['text'], 'user' => $this->mySlackId, 'edited' => ['user' => $this->mySlackId, 'ts' => $editTs]]]);
        },
        'slack.com/api/chat.delete' => function () use ($deleted) {
            StoreSlackMessage::make()->handle(['type' => 'message', 'subtype' => 'message_deleted', 'channel' => $this->channel->slack_id, 'channel_type' => 'channel', 'deleted_ts' => $deleted->ts]);

            return Http::response(['ok' => true, 'ts' => $deleted->ts]);
        },
        'slack.com/api/reactions.add' => Http::response(['ok' => true]),
    ]);
    $kinds = fn (SlackMessage $message) => Event::dispatched(SlackChannelMessageChanged::class)
        ->map(fn (array $args) => $args[0])
        ->filter(fn (SlackChannelMessageChanged $event) => $event->message->id === $message->id)
        ->map(fn (SlackChannelMessageChanged $event) => $event->kind)->values()->all();

    actingAs($this->me)->patchJson(($this->url)('grp.chat.staff.slack.messages.update'), ['ts' => $edited->ts, 'text' => 'Changed'])->assertOk()->assertJsonPath('message.is_edited', true);
    actingAs($this->me)->deleteJson(($this->url)('grp.chat.staff.slack.messages.delete'), ['ts' => $deleted->ts])->assertOk()->assertJsonPath('message.is_deleted', true);
    actingAs($this->me)->postJson(($this->url)('grp.chat.staff.slack.reactions.toggle'), ['ts' => $reacted->ts, 'name' => 'tada', 'add' => true])->assertOk();

    expect($edited->refresh()->text)->toBe('Changed')
        ->and($edited->versions()->count())->toBe(1)
        ->and(SlackMessage::withTrashed()->find($deleted->id)->trashed())->toBeTrue()
        ->and($kinds($edited))->toContain('changed')->not->toContain('new')
        ->and($kinds($deleted))->toBe(['deleted'])
        ->and($kinds($reacted))->toBe(['reaction']);
});
