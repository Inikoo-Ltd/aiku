<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 21 Nov 2026 16:30:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace Database\Seeders;

use App\Actions\Helpers\Media\StoreMediaFromFile;
use App\Models\Chat\SlackChannel;
use App\Models\Chat\SlackChannelBookmark;
use App\Models\Chat\SlackChannelMember;
use App\Models\Chat\SlackEmoji;
use App\Models\Chat\SlackFile;
use App\Models\Chat\SlackMessage;
use App\Models\Chat\SlackMessageReaction;
use App\Models\Chat\SlackSyncRun;
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
        SlackFile::where('slack_id', 'like', 'F'.self::PREFIX.'SHOW%')->get()->each->delete();
        SlackFile::where('slack_id', 'like', 'F'.self::PREFIX.'%')->delete();

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
        $this->threadsPinsAndBookmarks($general, $ids, now()->subHours(5));
        $this->files($general, $ignored, $ids, now()->subHours(3));
        foreach ([$general, $warehouse, $deploys] as $memberOf) {
            foreach ($ids as $slackId) {
                SlackChannelMember::create(['slack_channel_id' => $memberOf->id, 'slack_user_id' => $slackId, 'joined_at' => $start]);
            }
        }

        $this->syncHealth($group, [$general, $warehouse, $deploys]);

        $this->command?->info('Slack sample ready: open '.route('grp.chat.staff.slack.index').' and '.route('grp.devops.slack_sync'));
    }

    /**
     * @param  array<int, SlackChannel>  $keptChannels
     */
    private function syncHealth(int $group, array $keptChannels): void
    {
        $lastNight = now()->utc()->setTime(2, 40);
        if ($lastNight->isFuture()) {
            $lastNight->subDay();
        }
        foreach ($keptChannels as $keptChannel) {
            $keptChannel->update(['history_synced_at' => $lastNight]);
        }
        $runs = [
            'slack:sync-public-channels'    => [0, null],
            'slack:sync-users'              => [60, ['people' => 9, 'matched' => 2]],
            'slack:sync-channel-members'    => [65, ['channels' => 3, 'members' => 27]],
            'slack:sync-pins-and-bookmarks' => [70, ['channels' => 3, 'pinned' => 2, 'bookmarks' => 2]],
            'slack:sync-emojis'             => [75, ['emojis' => 2, 'removed' => 0, 'without_copy' => 0]],
        ];
        foreach ($runs as $command => [$minutes, $totals]) {
            SlackSyncRun::create(array_merge([
                'command'     => $command,
                'started_at'  => $lastNight->copy()->addMinutes($minutes),
                'finished_at' => $lastNight->copy()->addMinutes($minutes + 2),
                'totals'      => $totals,
            ], $totals ? [] : ['channels' => count($keptChannels), 'messages_read' => 40, 'messages_added' => 2]));
        }
        foreach (['copied' => [6, 'r2'], 'external' => [2, null], 'skipped' => [1, null], 'pending' => [3, null]] as $state => [$count, $disk]) {
            for ($i = 1; $i <= $count; $i++) {
                SlackFile::create([
                    'group_id'  => $group,
                    'slack_id'  => 'F'.self::PREFIX.strtoupper($state).$i,
                    'name'      => $state.'-'.$i.'.png',
                    'mime_type' => 'image/png',
                    'size'      => 250000 * $i,
                    'state'     => $state,
                    'disk'      => $disk,
                ]);
            }
        }
    }

    /**
     * Messages with files in every state the screen shows: copied images and documents, a copy on the large files
     * disk, links kept outside Slack, and files not copied, skipped or gone.
     *
     * @param  array<string, string>  $ids
     */
    private function files(SlackChannel $channel, SlackChannel $hidden, array $ids, Carbon $time): void
    {
        $group = $channel->group_id;
        $entry = fn (string $key, string $name, string $mime, int $size, array $extra = []) => array_merge(
            ['id' => 'F'.self::PREFIX.'SHOW'.strtoupper($key), 'name' => $name, 'title' => $name, 'mimetype' => $mime, 'filetype' => pathinfo($name, PATHINFO_EXTENSION), 'size' => $size],
            $extra
        );
        $row = fn (array $file, string $state, array $extra = []) => SlackFile::create(array_merge([
            'group_id' => $group, 'slack_id' => $file['id'], 'name' => $file['name'], 'mime_type' => $file['mimetype'], 'size' => $file['size'], 'mode' => $file['mode'] ?? 'hosted', 'state' => $state, 'data' => $file,
        ], $extra));
        $copied = function (array $file, string $contents) use ($row) {
            $path = tempnam(sys_get_temp_dir(), 'local-slack-file-');
            file_put_contents($path, $contents);
            $slackFile = $row($file + ['size' => strlen($contents)], 'copied', ['size' => strlen($contents)]);
            $media     = StoreMediaFromFile::run($slackFile, ['path' => $path, 'originalName' => $file['name'], 'extension' => pathinfo($file['name'], PATHINFO_EXTENSION) ?: 'bin', 'checksum' => md5_file($path)], 'slack_files', str_starts_with($file['mimetype'], 'image/') ? 'image' : 'file');
            $slackFile->update(['media_id' => $media->id]);
            @unlink($path);

            return $file;
        };
        $picture = function (int $width, int $height, array $rgb, string $label, string $format = 'png'): string {
            $image = imagecreatetruecolor($width, $height);
            imagefill($image, 0, 0, imagecolorallocate($image, ...$rgb));
            imagefilledrectangle($image, (int) ($width * 0.2), (int) ($height * 0.3), (int) ($width * 0.8), (int) ($height * 0.7), imagecolorallocate($image, 205, 170, 125));
            imagestring($image, 5, 12, 12, $label, imagecolorallocate($image, 255, 255, 255));
            ob_start();
            $format === 'jpeg' ? imagejpeg($image, null, 85) : imagepng($image);

            return (string) ob_get_clean();
        };
        $pdf = "%PDF-1.4\n1 0 obj<</Type/Catalog/Pages 2 0 R>>endobj\n2 0 obj<</Type/Pages/Kids[3 0 R]/Count 1>>endobj\n3 0 obj<</Type/Page/Parent 2 0 R/MediaBox[0 0 300 200]>>endobj\ntrailer<</Root 1 0 R>>\n%%EOF\n";

        $photos = [
            $copied($entry('photo1', 'parcel-front.jpg', 'image/jpeg', 0), $picture(1600, 1200, [70, 90, 120], 'Parcel, front', 'jpeg')),
            $copied($entry('photo2', 'parcel-corner.png', 'image/png', 0), $picture(900, 1400, [120, 70, 70], 'Crushed corner')),
            $copied($entry('photo3', 'label.png', 'image/png', 0), $picture(1200, 500, [60, 110, 80], 'Label')),
        ];
        $this->message($channel, $ids['ana'], 'The parcel for order `ORD-104233` arrived like this', $time, ['files' => $photos]);
        $this->message($hidden, $ids['ana'], 'Same photo, shared where nobody may look', $time, ['files' => [$photos[0]]]);

        $this->message($channel, $ids['tom'], 'Supplier price list and the stock sheet', $time->addMinutes(9), ['files' => [
            $copied($entry('pdf', 'supplier-price-list-2026.pdf', 'application/pdf', 0), $pdf),
            $copied($entry('sheet', 'stock-count-aisle-4.csv', 'text/csv', 0), "sku,counted\nABC-01,24\nABC-02,18\n"),
            $copied($entry('photo4', 'pallet.png', 'image/png', 0), $picture(800, 800, [90, 90, 90], 'Pallet')),
        ]]);

        $this->message($channel, $ids['mei'], 'A web page and a drawing: both must download, never open inside aiku', $time->addMinutes(7), ['files' => [
            $copied($entry('html', 'newsletter.html', 'text/html', 0), '<html><body><h1>Newsletter</h1><script>document.title = "ran in aiku"</script></body></html>'),
            $copied($entry('svg', 'logo.svg', 'image/svg+xml', 0), '<svg xmlns="http://www.w3.org/2000/svg" width="80" height="80"><script>alert(1)</script><circle cx="40" cy="40" r="30"/></svg>'),
            $copied($entry('tricky', '../../etc/passwd "quoted" <b>.txt', 'text/plain', 0), "just text\n"),
        ]]);

        $video = $entry('video', 'Warehouse induction 2026.mp4', 'video/mp4', 240 * 1024 * 1024);
        $row($video, 'copied', ['disk' => 'staff-chat-r2', 'path' => 'slack-files/'.$video['id'].'/warehouse-induction-2026.mp4']);
        $this->message($channel, $ids['jonas'], 'Induction video for the new starters', $time->addMinutes(12), ['files' => [$video]]);

        $doc     = $entry('gdoc', 'Returns procedure', 'application/vnd.google-apps.document', 0, ['mode' => 'external', 'external_type' => 'gdrive', 'external_url' => 'https://docs.google.com/document/d/local-sample/edit', 'url_private' => 'https://docs.google.com/document/d/local-sample/edit']);
        $badLink = $entry('badlink', 'Odd link', 'text/plain', 0, ['mode' => 'external', 'external_url' => 'javascript:alert(1)', 'url_private' => 'javascript:alert(1)']);
        $row($doc, 'external');
        $row($badLink, 'external');
        $this->message($channel, $ids['ana'], 'The procedure is in this document', $time->addMinutes(5), ['files' => [$doc, $badLink]]);

        $waiting = $entry('waiting', 'not-copied-yet.png', 'image/png', 480000);
        $copying = $entry('copying', 'being-copied.pdf', 'application/pdf', 2400000);
        $failed  = $entry('failed', 'copy-failed.docx', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document', 88000);
        $huge    = $entry('huge', 'full-site-backup.zip', 'application/zip', 3 * 1024 * 1024 * 1024);
        $gone    = $entry('gone', 'old-photo.jpg', 'image/jpeg', 0, ['mode' => 'tombstone']);
        $row($copying, 'copying');
        $row($failed, 'failed', ['reason' => 'http 500']);
        $row($huge, 'skipped', ['reason' => 'larger than 1 GB']);
        $row($gone, 'not_available', ['reason' => 'Slack shows it as tombstone', 'mode' => 'tombstone']);
        $this->message($channel, $ids['tom'], 'Files aiku holds no copy of', $time->addMinutes(4), ['files' => [$waiting, $copying, $failed, $huge, $gone]]);
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
     * A long thread (more than one page of replies) with a reply also sent to the channel and a pinned reply, a thread
     * whose parent was deleted, a pinned top-level message, and bookmarks, one of them with an unsafe link.
     *
     * @param  array<string, string>  $ids
     */
    private function threadsPinsAndBookmarks(SlackChannel $channel, array $ids, Carbon $time): void
    {
        $people = [$ids['ana'], $ids['tom'], $ids['mei'], $ids['me'], $ids['jonas']];

        $parent = $this->message($channel, $ids['tom'], 'Stock count plan for next week: who takes which aisle? Reply in the thread :thread:', $time);
        $parent->update(['thread_ts' => $parent->ts, 'pinned_at' => $time->copy()->addMinutes(5), 'pinned_by_slack_user_id' => $ids['ana']]);
        $last = $time->copy();
        for ($r = 1; $r <= 62; $r++) {
            $last->addMinutes(1);
            $extra = ['thread_ts' => $parent->ts];
            $text  = $r === 1 ? 'I take aisles 1 to 4 :+1:' : "Reply $r: aisle ".(($r % 12) + 1).' done';
            if ($r === 30) {
                $extra['subtype'] = 'thread_broadcast';
                $text             = 'Halfway there, aisles 1 to 6 counted';
            }
            if ($r === 3) {
                $extra['pinned_at']               = $last->copy()->addMinute();
                $extra['pinned_by_slack_user_id'] = $ids['tom'];
                $text                             = 'Rule for the count: scan every box, never type the quantity';
            }
            $this->message($channel, $people[$r % count($people)], $text, $last->copy(), [], $extra);
        }

        $gone = $this->message($channel, $ids['jonas'], 'This question was deleted later', $last->copy()->addMinutes(5));
        $gone->update(['thread_ts' => $gone->ts]);
        $this->message($channel, $ids['ana'], 'The answers stay even though the question is gone', $last->copy()->addMinutes(6), [], ['thread_ts' => $gone->ts]);
        $gone->delete();

        $this->message($channel, $ids['ana'], 'Office hours: 8:00 to 17:00, Friday until 15:00', $last->copy()->addMinutes(8), [], [
            'pinned_at' => $last->copy()->addMinutes(9), 'pinned_by_slack_user_id' => $ids['tom'],
        ]);

        foreach ([
            ['title' => 'Staff handbook', 'link' => 'https://example.com/handbook', 'emoji' => ':books:'],
            ['title' => 'Warehouse map', 'link' => 'https://example.com/map', 'emoji' => ':local-aiku:'],
            ['title' => 'Email HR', 'link' => 'mailto:hr@example.com', 'emoji' => null],
            ['title' => 'Unsafe link, never linked', 'link' => 'javascript:alert(1)', 'emoji' => ':warning:'],
        ] as $position => $bookmark) {
            SlackChannelBookmark::create(array_merge($bookmark, [
                'slack_channel_id' => $channel->id,
                'slack_id'         => 'Bk'.self::PREFIX.$position,
                'position'         => (string) $position,
            ]));
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
