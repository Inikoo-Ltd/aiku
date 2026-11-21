<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 21 Nov 2026 21:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

/** @noinspection PhpUnhandledExceptionInspection */

use App\Actions\Chat\Staff\Json\SearchSlackMessages;
use App\Actions\Chat\Staff\Slack\StoreSlackMessage;
use App\Models\Chat\SlackChannel;
use App\Models\Chat\SlackMessage;
use App\Models\Chat\SlackUser;
use App\Models\SysAdmin\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia;

use function Pest\Laravel\actingAs;

beforeAll(function () {
    loadDB();
});

beforeEach(function () {
    \Illuminate\Support\Facades\Config::set('inertia.pages.paths', [resource_path('js/Pages/Grp')]);
    $this->group = createGroup();
    $this->s     = Str::upper(Str::random(6));
    $this->word  = 'kw'.Str::lower(Str::random(8));

    $newUser        = fn () => User::factory()->create(['group_id' => $this->group->id, 'language_id' => User::where('group_id', $this->group->id)->value('language_id') ?? 1]);
    $this->viewer   = $newUser();
    $this->stranger = $newUser();
    SlackUser::create(['slack_id' => "UVIEW{$this->s}", 'user_id' => $this->viewer->id, 'email' => $this->viewer->email, 'real_name' => 'Viewer Person']);
    SlackUser::create(['slack_id' => "UANN{$this->s}", 'real_name' => 'Ann Author']);
    SlackUser::create(['slack_id' => "UBOT{$this->s}", 'real_name' => 'Ticket Bot', 'is_bot' => true]);

    $channel             = fn (string $key, array $attributes = []) => SlackChannel::create(array_merge(['group_id' => $this->group->id, 'slack_id' => "C$key{$this->s}", 'name' => strtolower($key)."-{$this->s}", 'is_member' => true], $attributes));
    $this->general       = $channel('GENERAL');
    $this->warehouse     = $channel('WAREHOUSE');
    $this->ignored       = $channel('IGNORED', ['is_ignored' => true]);
    $this->private       = $channel('PRIVATE', ['is_public' => false]);
    $this->deletedInSlack = $channel('GONE', ['deleted_in_slack_at' => now()]);

    $this->start = now()->subDays(10)->startOfMinute();
    $this->store = function (SlackChannel $channel, int $minute, string $text, array $attributes = []) {
        $ts = sprintf('%d.%06d', $this->start->timestamp + $minute * 60, $minute);

        return SlackMessage::create(array_merge([
            'slack_channel_id' => $channel->id,
            'ts'               => $ts,
            'slack_user_id'    => "UANN{$this->s}",
            'text'             => $text,
            'sent_at'          => $this->start->copy()->addMinutes($minute),
            'data'             => [],
        ], $attributes));
    };
    $this->search = fn (User $user, array $filters, int $perPage = SearchSlackMessages::PER_PAGE) => SearchSlackMessages::make()->handle($user, $filters, $perPage);
    $this->ids    = fn (array $result) => array_column($result['data'], 'id');
});

test('Slack search finds a message by a few of its words in every channel the person may see and nowhere else, INI-442', function () {
    $w     = $this->word;
    $store = $this->store;

    $plain    = $store($this->general, 1, "The pallet labels for the blue bay $w are ready");
    $other    = $store($this->warehouse, 2, "Moved the *$w* pallets to aisle 4");
    $bot      = $store($this->warehouse, 3, '', ['slack_user_id' => "UBOT{$this->s}", 'data' => [
        'bot_id' => 'BTICKETS',
        'blocks' => [['type' => 'section', 'text' => ['type' => 'mrkdwn', 'text' => "New ticket about $w from a customer"]]],
    ]]);
    $file     = $store($this->general, 4, '', ['data' => ['files' => [['id' => 'F1', 'name' => "stock-$w.xlsx", 'title' => 'Stock count']]]]);
    $reply    = $store($this->general, 5, "Answer about $w in the thread", ['thread_ts' => $plain->ts]);
    $deleted  = $store($this->general, 6, "Deleted $w message");
    $deleted->delete();
    $system   = $store($this->general, 7, "<@UANN{$this->s}> has joined $w", ['subtype' => 'channel_join']);
    $hidden   = [
        $store($this->ignored, 8, "Ignored $w"),
        $store($this->private, 9, "Private $w"),
        $store($this->deletedInSlack, 10, "Gone $w"),
    ];

    $result = ($this->search)($this->viewer, ['q' => "$w pallet"]);
    expect(($this->ids)($result))->toBe([$other->id, $plain->id])
        ->and($result['data'][1]['channel'])->toBe(['id' => $this->general->id, 'name' => "general-{$this->s}"])
        ->and($result['data'][1]['author'])->toBe(['name' => 'Ann Author', 'avatar' => null, 'is_bot' => false])
        ->and(collect($result['data'][1]['segments'])->where('hit', true)->pluck('text')->all())->toBe(['pallet', $w])
        ->and(collect($result['data'][0]['segments'])->pluck('text')->implode(''))->toBe("Moved the $w pallets to aisle 4");

    $everything = ($this->search)($this->viewer, ['q' => $w]);
    expect(($this->ids)($everything))->toBe([$reply->id, $file->id, $bot->id, $other->id, $plain->id])
        ->and(collect($everything['data'])->firstWhere('id', $bot->id)['author'])->toBe(['name' => 'Ticket Bot', 'avatar' => null, 'is_bot' => true])
        ->and(($this->ids)($everything))->not->toContain($deleted->id)->not->toContain($system->id);
    foreach ($hidden as $message) {
        expect(($this->ids)($everything))->not->toContain($message->id);
    }

    expect(($this->ids)(($this->search)($this->viewer, ['q' => $w, 'channel' => $this->ignored->id])))->toBe([])
        ->and(($this->ids)(($this->search)($this->viewer, ['q' => $w, 'channel' => $this->private->id])))->toBe([]);

    expect(($this->search)($this->stranger, ['q' => $w])['data'])->toBe([]);
    actingAs($this->stranger)->getJson(route('grp.chat.staff.slack.search', ['q' => $w]))->assertOk()->assertJsonPath('data', []);
    actingAs($this->stranger)->getJson(route('grp.chat.staff.slack.search.people'))->assertOk()->assertExactJson([]);

    $people = actingAs($this->viewer)->getJson(route('grp.chat.staff.slack.search.people'))->assertOk()->json();
    expect(collect($people)->firstWhere('slack_id', "UVIEW{$this->s}")['name'])->toBe($this->viewer->chatName())
        ->and(collect($people)->firstWhere('slack_id', "UANN{$this->s}")['name'])->toBe('Ann Author');
});

test('Slack search filters by channel, person, date range and file, newest first, page by page, INI-442', function () {
    $w     = $this->word;
    $store = $this->store;

    $first  = $store($this->general, 1, "$w one");
    $second = $store($this->warehouse, 60 * 24 * 2, "$w two", ['slack_user_id' => "UVIEW{$this->s}"]);
    $third  = $store($this->general, 60 * 24 * 4, "$w three", ['data' => ['files' => [['id' => 'F2', 'name' => 'count.csv']]]]);
    $fourth = $store($this->warehouse, 60 * 24 * 6, "$w four");

    expect(($this->ids)(($this->search)($this->viewer, ['q' => $w])))->toBe([$fourth->id, $third->id, $second->id, $first->id])
        ->and(($this->ids)(($this->search)($this->viewer, ['q' => $w, 'channel' => $this->warehouse->id])))->toBe([$fourth->id, $second->id])
        ->and(($this->ids)(($this->search)($this->viewer, ['q' => $w, 'person' => "UVIEW{$this->s}"])))->toBe([$second->id])
        ->and(($this->ids)(($this->search)($this->viewer, ['q' => $w, 'has_file' => true])))->toBe([$third->id])
        ->and(($this->ids)(($this->search)($this->viewer, [
            'q'    => $w,
            'from' => $this->start->copy()->addDays(1)->toIso8601String(),
            'to'   => $this->start->copy()->addDays(5)->toIso8601String(),
        ])))->toBe([$third->id, $second->id])
        ->and(($this->search)($this->viewer, ['q' => $w])['data'][2]['author']['name'])->toBe($this->viewer->chatName());

    $pageOne = actingAs($this->viewer)->getJson(route('grp.chat.staff.slack.search', ['q' => $w]))->json();
    expect($pageOne['next_cursor'])->toBeNull();

    $firstPage = ($this->search)($this->viewer, ['q' => $w], 3);
    expect(($this->ids)($firstPage))->toBe([$fourth->id, $third->id, $second->id])
        ->and($firstPage['next_cursor'])->not->toBeNull();

    $secondPage = actingAs($this->viewer)->getJson(route('grp.chat.staff.slack.search', ['q' => $w, 'cursor' => $firstPage['next_cursor']]))->assertOk()->json();
    expect(array_column($secondPage['data'], 'id'))->toBe([$first->id])
        ->and($secondPage['next_cursor'])->toBeNull();
});

test('Slack search takes any typed text safely: quotes, operators, punctuation and very short queries, and matches words from their start, INI-442', function () {
    $w     = $this->word;
    $store = $this->store;

    $order    = $store($this->general, 1, "Can you look at order `ORD-104233$w`? It shows twice");
    $supplier = $store($this->general, 2, "The supplier$w agreed to ship on Friday");
    $slovak   = $store($this->general, 3, "Číslo objednávky $w je hotové");
    $code     = $store($this->general, 4, "Box AWD197367$w left today");
    $phrase   = $store($this->general, 5, "blue pallet $w");
    $notPhrase = $store($this->general, 6, "pallet is blue $w");

    $hostile = [
        "'", '"', "''", '\\', '&', '|', '!', '<->', ':*', '()', "$w'", "\"$w", "$w & | ! <-> :* ( ) '",
        "'; DROP TABLE slack_messages; --", "$w\0", "\x01\x02 $w", "$w %_", "-$w", "or", "$w or", "\"unclosed $w", "\xff\xfe$w",
    ];
    foreach ($hostile as $query) {
        actingAs($this->viewer)->getJson(route('grp.chat.staff.slack.search', ['q' => $query]))->assertOk();
    }
    expect(SlackMessage::where('slack_channel_id', $this->general->id)->count())->toBe(6);

    foreach (['x', '!!', '"', '  a  '] as $short) {
        expect(($this->search)($this->viewer, ['q' => $short]))->toBe(['data' => [], 'next_cursor' => null, 'too_short' => true]);
    }
    actingAs($this->viewer)->getJson(route('grp.chat.staff.slack.search', ['q' => str_repeat('a', 201)]))->assertUnprocessable();
    actingAs($this->viewer)->getJson(route('grp.chat.staff.slack.search'))->assertUnprocessable();

    $found = fn (string $query) => ($this->ids)(($this->search)($this->viewer, ['q' => $query]));

    expect($found("104233$w"))->toBe([$order->id])
        ->and($found("ORD-104233$w"))->toBe([$order->id])
        ->and($found("supplier$w"))->toBe([$supplier->id])
        ->and($found('supplier'.substr($w, 0, 4)))->toContain($supplier->id)
        ->and($found("objednavky $w"))->toBe([$slovak->id])
        ->and($found("ČÍSLO $w"))->toBe([$slovak->id])
        ->and($found('AWD1973'))->toContain($code->id)
        ->and($found("197367$w"))->toBe([])
        ->and($found("\"blue pallet\" $w"))->toBe([$phrase->id])
        ->and($found("pallet $w -friday"))->toBe([$notPhrase->id, $phrase->id])
        ->and($found("supplier$w or objednavky$w"))->toBe([$supplier->id])
        ->and($found("$w'"))->toBe([$notPhrase->id, $phrase->id, $slovak->id]);

    $hits = collect(($this->search)($this->viewer, ['q' => "objednavky $w"])['data'][0]['segments'])->where('hit', true)->pluck('text')->all();
    expect($hits)->toBe(['objednávky', $w]);
});

test('the Slack search entry follows edits, is filled for old messages by a resumable command, and a result opens its channel at the message or its thread, INI-442', function () {
    $w     = $this->word;
    $store = $this->store;

    $message = StoreSlackMessage::make()->store($this->general, ['ts' => sprintf('%d.000001', $this->start->timestamp), 'user' => "UANN{$this->s}", 'text' => "first wording $w"]);
    expect(($this->ids)(($this->search)($this->viewer, ['q' => "first $w"])))->toBe([$message->id]);

    StoreSlackMessage::make()->store($this->general, ['ts' => $message->ts, 'user' => "UANN{$this->s}", 'text' => "second wording $w", 'edited' => ['ts' => sprintf('%d.000001', $this->start->timestamp + 30)]]);
    expect(($this->ids)(($this->search)($this->viewer, ['q' => "first $w"])))->toBe([])
        ->and(($this->ids)(($this->search)($this->viewer, ['q' => "second $w"])))->toBe([$message->id]);

    DB::table('slack_messages')->where('id', $message->id)->update(['search_vector' => null]);
    expect(($this->ids)(($this->search)($this->viewer, ['q' => "second $w"])))->toBe([]);
    $this->artisan('slack:refresh-message-search')->assertSuccessful();
    expect(($this->ids)(($this->search)($this->viewer, ['q' => "second $w"])))->toBe([$message->id]);

    for ($i = 1; $i <= 70; $i++) {
        $store($this->warehouse, $i, "filler $i");
    }
    $parent = $store($this->warehouse, 20, '', ['ts' => sprintf('%d.%06d', $this->start->timestamp + 20 * 60, 999), 'text' => "thread start $w", 'thread_ts' => sprintf('%d.%06d', $this->start->timestamp + 20 * 60, 999)]);
    $reply  = $store($this->warehouse, 21, "thread answer $w", ['ts' => sprintf('%d.%06d', $this->start->timestamp + 21 * 60, 999), 'thread_ts' => $parent->ts]);

    $results = collect(($this->search)($this->viewer, ['q' => "thread $w"])['data'])->keyBy('id');
    expect($results[$reply->id]['is_reply'])->toBeTrue()
        ->and($results[$reply->id]['url'])->toBe(route('grp.chat.staff.slack.show', ['slackChannel' => $this->warehouse->id, 'around' => $parent->ts, 'thread' => $parent->ts, 'reply' => $reply->ts]))
        ->and($results[$parent->id]['is_reply'])->toBeFalse()
        ->and($results[$parent->id]['url'])->toBe(route('grp.chat.staff.slack.show', ['slackChannel' => $this->warehouse->id, 'around' => $parent->ts]));

    $stream = null;
    actingAs($this->viewer)->get($results[$reply->id]['url'])->assertOk()
        ->assertInertia(function (AssertableInertia $page) use (&$stream, $parent, $reply) {
            $page->component('Chat/StaffMessaging')
                ->where('slack_around_ts', $parent->ts)
                ->where('slack_thread_ts', $parent->ts)
                ->where('slack_reply_ts', $reply->ts)
                ->loadDeferredProps(function (AssertableInertia $reload) use (&$stream) {
                    $stream = $reload->toArray()['props']['slack_messages'];
                });
        });
    expect(array_column($stream['data'], 'id'))->toContain($parent->id)
        ->and(array_column($stream['data'], 'id'))->not->toContain(SlackMessage::where('slack_channel_id', $this->warehouse->id)->where('text', 'filler 70')->value('id'));

    actingAs($this->stranger)->get($results[$reply->id]['url'])->assertForbidden();
});
