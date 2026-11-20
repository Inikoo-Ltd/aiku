<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 21 Nov 2026 16:30:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace Database\Seeders;

use App\Actions\Helpers\Media\StoreMediaFromFile;
use App\Models\Chat\SlackChannel;
use App\Models\Chat\SlackEmoji;
use App\Models\Chat\SlackMessage;
use App\Models\Chat\SlackMessageReaction;
use App\Models\Chat\SlackUser;
use App\Models\SysAdmin\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use RuntimeException;

/**
 * LOCAL ONLY: made-up Slack channels, people and messages to look at the staff chat Slack screen, since the sync only
 * runs in production. Every row it writes carries the LOCAL prefix and is replaced on each run.
 * php artisan db:seed --class=LocalSlackSampleSeeder links the local aiku and raul accounts (SLACK_SAMPLE_USERNAMES=a,b for others).
 */
class LocalSlackSampleSeeder extends Seeder
{
    private const string PREFIX = 'LOCAL';

    private int $sequence = 0;

    public function run(): void
    {
        if (!app()->environment('local')) {
            throw new RuntimeException('LocalSlackSampleSeeder runs on a local environment only.');
        }

        $viewers = User::whereIn('username', explode(',', env('SLACK_SAMPLE_USERNAMES', 'aiku,raul')))->orderBy('id')->get();
        $viewer  = $viewers->firstOrFail();
        $group   = $viewer->group_id;

        SlackChannel::where('slack_id', 'like', 'C'.self::PREFIX.'%')->delete();
        SlackUser::where('slack_id', 'like', 'U'.self::PREFIX.'%')->orWhere('slack_id', 'like', 'B'.self::PREFIX.'%')->delete();
        SlackEmoji::where('name', 'like', 'local-%')->delete();

        $people = [
            'me'    => ['name' => strtolower($viewer->username), 'real_name' => $viewer->contact_name ?: $viewer->username, 'email' => strtolower((string) $viewer->email), 'user_id' => $viewer->id],
            'ana'   => ['name' => 'ana.p', 'real_name' => 'Ana Pereira', 'display_name' => 'Ana'],
            'tom'   => ['name' => 'tom.b', 'real_name' => 'Tom Bright'],
            'mei'   => ['name' => 'mei.l', 'real_name' => 'Mei Lam', 'display_name' => 'Mei'],
            'jonas' => ['name' => 'jonas.n', 'real_name' => 'Jonas Nowak'],
            'left'  => ['name' => 'old.colleague', 'real_name' => 'Former Colleague', 'is_deleted' => true],
            'bot'   => ['name' => 'deploy-bot', 'real_name' => 'Deploy Bot', 'is_bot' => true],
        ];
        foreach ($viewers->skip(1)->values() as $index => $other) {
            $people['viewer'.$index] = ['name' => strtolower($other->username), 'real_name' => $other->contact_name ?: $other->username, 'email' => strtolower((string) $other->email), 'user_id' => $other->id];
        }
        $ids = [];
        foreach ($people as $key => $attributes) {
            $ids[$key] = 'U'.self::PREFIX.strtoupper($key);
            SlackUser::create(array_merge(['slack_id' => $ids[$key], 'is_bot' => false, 'is_deleted' => false], $attributes));
        }

        $this->customEmoji();

        $channel = fn (string $key, string $name, string $topic, array $extra = []) => SlackChannel::create(array_merge([
            'group_id'  => $group,
            'slack_id'  => 'C'.self::PREFIX.strtoupper($key),
            'name'      => $name,
            'is_member' => true,
            'data'      => ['topic' => ['value' => $topic], 'purpose' => ['value' => $topic]],
        ], $extra));

        $general   = $channel('general', 'general', 'Company-wide news and questions');
        $warehouse = $channel('warehouse', 'warehouse-floor', 'Picking, packing and stock counts');
        $deploys   = $channel('deploys', 'deploys', 'Release notes posted by the deploy bot');
        $channel('random', 'random', 'Anything else');
        $archived = $channel('oldproject', 'spring-launch-2025', 'Finished project', ['is_archived' => true]);
        $ignored  = $channel('mirror', 'ticket-mirror', 'Never shown: ignored', ['is_ignored' => true]);
        $channel('private', 'private-pay-talk', 'Never shown: private', ['is_public' => false]);

        $start = now()->subDays(21)->setTime(8, 0);
        $this->conversation($general, $ids, $start, 180, $warehouse->slack_id);
        $this->conversation($warehouse, $ids, $start->copy()->addHours(3), 120, $general->slack_id);
        $this->deploys($deploys, $ids, $start);
        $this->conversation($archived, $ids, $start->copy()->subMonths(8), 25, $general->slack_id);
        $this->conversation($ignored, $ids, $start, 5, $general->slack_id);

        $this->command?->info('Slack sample ready: open '.route('grp.chat.staff.slack.index'));
    }

    private function customEmoji(): void
    {
        $image = imagecreatetruecolor(64, 64);
        imagefill($image, 0, 0, imagecolorallocate($image, 255, 140, 0));
        imagefilledellipse($image, 32, 32, 40, 40, imagecolorallocate($image, 255, 255, 255));
        $path = tempnam(sys_get_temp_dir(), 'local-emoji-').'.png';
        imagepng($image, $path);

        $emoji = SlackEmoji::create(['name' => 'local-aiku', 'url' => 'https://emoji.slack-edge.com/local/aiku.png']);
        $media = StoreMediaFromFile::run($emoji, ['path' => $path, 'originalName' => 'local-aiku', 'extension' => 'png', 'checksum' => md5_file($path)], 'slack_emojis');
        $emoji->update(['media_id' => $media->id]);
        SlackEmoji::create(['name' => 'local-ok', 'alias_for' => 'local-aiku']);
        @unlink($path);
    }

    /**
     * @param  array<string, string>  $ids
     */
    private function conversation(SlackChannel $channel, array $ids, Carbon $start, int $count, string $otherChannel): void
    {
        $humans = array_values(array_filter([$ids['me'], $ids['ana'], $ids['tom'], $ids['viewer0'] ?? null, $ids['mei'], $ids['jonas'], $ids['left']]));
        $lines  = [
            'Morning all :wave:',
            'Has anyone seen the pallet labels for the *blue* bay?',
            'Done :white_check_mark:',
            'I will check after lunch',
            'The courier is late again _sigh_',
            'Stock count for aisle 4 is finished, numbers in the sheet',
            "Can you look at order `ORD-104233`? It shows twice",
            '<@'.$ids['ana'].'> could you take this one?',
            'Moved to <#'.$otherChannel.'> as it is about stock',
            'Docs are here: <https://example.com/handbook|staff handbook>',
            'Thanks! :local-aiku:',
            '~Friday~ Thursday works better for me',
            'All good :local-ok: :+1::skin-tone-3:',
            "Steps:\n• open the order\n• press *Recalculate*\n• save",
            "&gt; can we ship partial orders?\nYes, if the customer agreed",
            "```php artisan stock:recount --aisle=4\nDone in 12s```",
            '<!here> short meeting at 3pm',
            'Box sizes changed, see attached',
            'Does 10:30 work?',
        ];

        $time = $start->copy();
        for ($i = 0; $i < $count; $i++) {
            $time->addMinutes([1, 2, 3, 15, 45, 140, 600][($i * 7 + $count) % 7]);
            $author = match ($i) {
                $count - 4, $count - 3 => 'U'.self::PREFIX.'NOBODY',
                $count - 2             => null,
                default                => $humans[($i * 5 + intdiv($i, 3)) % count($humans)],
            };
            $text   = $lines[($i * 11) % count($lines)];
            $data   = [];
            $extra  = [];

            if ($i % 37 === 5) {
                $text = str_repeat('This is a long message that keeps going to check how the stream wraps text over many lines. ', 8)
                    ."\n\nSecond paragraph with a link https://example.com/a/very/long/path/that/should/wrap/nicely/on/small/screens/too";
            }
            if ($i % 23 === 4) {
                $extra['edited_at'] = $time->copy()->addMinutes(2);
                $data['edited']     = ['user' => $author, 'ts' => $this->ts($time->copy()->addMinutes(2))];
            }
            if ($i % 41 === 9) {
                $extra['pinned_at']               = $time->copy()->addHour();
                $extra['pinned_by_slack_user_id'] = $ids['ana'];
            }
            if ($i % 29 === 3) {
                $text          = '';
                $data['files'] = [['id' => 'F'.self::PREFIX.$i, 'name' => "stock-count-week-$i.xlsx", 'title' => 'Stock count']];
            }
            if ($i % 31 === 7) {
                $text                  = '<@'.$author.'> has joined the channel';
                $extra['subtype']      = 'channel_join';
            }

            $message = $this->message($channel, $author, $text, $time, $data, $extra);

            if ($i % 17 === 2) {
                $replies = 3 + $i % 4;
                $last    = $time->copy();
                for ($r = 0; $r < $replies; $r++) {
                    $last->addMinutes(4);
                    $this->message($channel, $humans[($i + $r + 1) % count($humans)], 'Reply '.($r + 1).' in the thread', $last, [], ['thread_ts' => $message->ts]);
                }
                $data = $message->data;
                $data['reply_count']  = $replies;
                $data['latest_reply'] = $this->ts($last);
                $message->update(['thread_ts' => $message->ts, 'data' => $data]);
                if ($i % 34 === 2) {
                    $this->message($channel, $humans[$i % count($humans)], 'Sharing the answer with everyone: yes, partial shipping is fine', $last->copy()->addMinute(), [], ['thread_ts' => $message->ts, 'subtype' => 'thread_broadcast']);
                }
            }
            if ($i % 6 === 1) {
                foreach (array_slice(['+1', 'local-aiku', 'tada', 'eyes', 'heart'], 0, 1 + $i % 3) as $k => $name) {
                    foreach (array_slice($humans, $k, 1 + ($i + $k) % 4) as $reactor) {
                        SlackMessageReaction::create(['slack_message_id' => $message->id, 'slack_user_id' => $reactor, 'name' => $name]);
                    }
                }
            }
            if ($i % 19 === 11) {
                $message->delete();
            }
        }
    }

    /**
     * @param  array<string, string>  $ids
     */
    private function deploys(SlackChannel $channel, array $ids, Carbon $start): void
    {
        $time = $start->copy();
        for ($i = 1; $i <= 30; $i++) {
            $time->addHours(14);
            $version = "2026.11.$i";
            $this->message($channel, $ids['bot'], "Deployed $version", $time, [
                'bot_id'      => 'B'.self::PREFIX.'DEPLOY',
                'bot_profile' => ['name' => 'Deploy Bot'],
                'blocks'      => [
                    ['type' => 'header', 'text' => ['type' => 'plain_text', 'text' => "Release $version"]],
                    ['type' => 'section', 'text' => ['type' => 'mrkdwn', 'text' => "*Deployed* to production by <@{$ids['tom']}> :rocket:"]],
                    ['type' => 'section', 'fields' => [
                        ['type' => 'mrkdwn', 'text' => "*Commits*\n".(3 + $i % 9)],
                        ['type' => 'mrkdwn', 'text' => "*Duration*\n".(40 + $i)."s"],
                    ]],
                    ['type' => 'divider'],
                    ['type' => 'context', 'elements' => [['type' => 'mrkdwn', 'text' => 'Triggered from main']]],
                    ['type' => 'actions', 'elements' => [['type' => 'button', 'text' => ['type' => 'plain_text', 'text' => 'Open release']]]],
                ],
                'attachments' => $i % 4 === 0 ? [[
                    'color'      => '#e01e5a',
                    'title'      => 'One test was flaky',
                    'title_link' => 'https://example.com/ci/'.$i,
                    'text'       => 'Retried once and passed',
                    'footer'     => 'CI',
                ]] : [],
            ]);
        }
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  array<string, mixed>  $extra
     */
    private function message(SlackChannel $channel, ?string $author, string $text, Carbon $time, array $data = [], array $extra = []): SlackMessage
    {
        $ts = $this->ts($time);

        return SlackMessage::create(array_merge([
            'slack_channel_id' => $channel->id,
            'ts'               => $ts,
            'slack_user_id'    => $author,
            'text'             => $text,
            'sent_at'          => $time->copy(),
            'data'             => array_merge(['type' => 'message', 'user' => $author, 'text' => $text, 'ts' => $ts], $data),
        ], $extra));
    }

    private function ts(Carbon $time): string
    {
        return sprintf('%d.%06d', $time->timestamp, ++$this->sequence);
    }
}
