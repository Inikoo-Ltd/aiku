<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 20 Nov 2026 16:20:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Chat\Staff\Slack;

use App\Enums\CRM\Livechat\SlackFileStateEnum;
use App\Models\Chat\SlackChannel;
use App\Models\Chat\SlackFile;
use App\Models\Chat\SlackMessage;
use Illuminate\Console\Command;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Sleep;
use Laravel\Nightwatch\Facades\Nightwatch;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Copies the files of Slack messages already kept in aiku, skipping files already dealt with, so it can be stopped and run again.
 * Downloads go to the low-priority queue one at a time, with a pause between them.
 */
class CopySlackMessageFiles
{
    use AsAction;

    public string $commandSignature = 'slack:copy-message-files
        {--dry-run : Only say how many files would be copied and their total size}
        {--channel= : Only this channel, by Slack id or name}
        {--retry-failed : Try again the files that failed before, once their cause is fixed}
        {--private : The files of private channels and DMs instead, copied to members-only storage}
        {--limit= : Stop after queueing this many downloads}
        {--pause=3 : Seconds between queued downloads, and between checks while the queue is busy}';

    public const int MAX_WAITING_JOBS = 20;

    public string $commandDescription = 'Copy into aiku the files of the Slack messages already kept';

    /**
     * @return array{queued: int, recorded: int}
     */
    public function handle(?SlackChannel $channel = null, ?int $limit = null, float $pause = 3, bool $retryFailed = false, bool $private = false): array
    {
        $totals = ['queued' => 0, 'recorded' => 0];
        $seen    = [];
        $waiting = [];

        $messages = SlackMessage::query()
            ->select(['id', 'slack_channel_id', 'slack_user_id', 'data'])
            ->whereIn('slack_channel_id', self::channels($private)->when($channel, fn ($query) => $query->whereKey($channel->id))->select('id'))
            ->whereRaw("jsonb_typeof(data->'files') = 'array'");

        foreach ($messages->lazyById(200) as $message) {
            $files = array_filter((array) Arr::get($message->data, 'files', []), fn ($file) => is_array($file) && Arr::get($file, 'id') && !isset($seen[$file['id']]));
            if (!$files || ($private && !CopySlackMessageFile::privateReadingToken($message))) {
                continue;
            }

            $done = SlackFile::whereIn('slack_id', array_column($files, 'id'))
                ->where('is_private', $private)
                ->whereNotIn('state', SlackFileStateEnum::toCopy($retryFailed))
                ->pluck('slack_id')
                ->flip();

            foreach ($files as $file) {
                $seen[$file['id']] = true;
                if (isset($done[$file['id']])) {
                    continue;
                }

                if (CopySlackMessageFile::isSettledWithoutSlack($file)) {
                    CopySlackMessageFile::run($message, $file);
                    $totals['recorded']++;
                    continue;
                }

                if ($limit !== null && $totals['queued'] >= $limit) {
                    return $totals;
                }
                if ($totals['queued'] > 0) {
                    Sleep::for($pause)->seconds();
                }
                while (count($waiting = $this->stillWaiting($waiting, $private)) >= self::MAX_WAITING_JOBS) {
                    Sleep::for($pause)->seconds();
                }
                CopySlackMessageFile::dispatch($message->id, (string) $file['id']);
                $waiting[] = $file['id'];
                $totals['queued']++;
            }
        }

        return $totals;
    }

    /**
     * The downloads this run queued that have not finished; the queue is shared with other work, so its own length says nothing.
     *
     * @param  list<string>  $slackIds
     * @return list<string>
     */
    private function stillWaiting(array $slackIds, bool $private): array
    {
        if (!$slackIds) {
            return [];
        }
        $finished = SlackFile::whereIn('slack_id', $slackIds)->where('is_private', $private)->whereNotIn('state', SlackFileStateEnum::toCopy())->pluck('slack_id')->all();

        return array_values(array_diff($slackIds, $finished));
    }

    /**
     * @return \Illuminate\Database\Eloquent\Builder<SlackChannel>
     */
    private static function channels(bool $private): \Illuminate\Database\Eloquent\Builder
    {
        return $private
            ? SlackChannel::where(fn ($query) => $query->whereIn('id', SlackChannel::keptPrivate()->select('id'))->orWhereIn('id', SlackChannel::keptDirect()->select('id')))
            : SlackChannel::where('is_public', true)->where('is_ignored', false);
    }

    /**
     * Files not dealt with yet, each counted once however many messages share it, by what would happen to them;
     * files Slack sent only the id of are counted by what Slack said instead, as their size is known only once asked.
     *
     * @return array<string, array{files: int, bytes: int}>
     */
    public function dryRun(?SlackChannel $channel = null, bool $retryFailed = false, bool $private = false): array
    {
        $channelIds = self::channels($private)->when($channel, fn ($query) => $query->whereKey($channel->id))->pluck('id')->all();
        $rows = DB::select(
            "select category, count(*) as files, coalesce(sum(size), 0) as bytes from (
                select distinct on (f->>'id') f->>'id' as slack_id, (f->>'size')::bigint as size,
                    case
                        when f->>'mode' = 'external' then 'external'
                        when f->>'mode' in ('tombstone', 'hidden_by_limit') then 'not_available: '||(f->>'mode')
                        when f->>'file_access' in ('file_not_found', 'file_deleted') then 'not_available: '||(f->>'file_access')
                        when coalesce(f->>'url_private_download', f->>'url_private') is null then 'ask Slack: '||coalesce(f->>'file_access', f->>'mode', 'no link')
                        when coalesce(f->>'url_private_download', f->>'url_private') !~ '^https://([a-z0-9-]+\\.)*slack\\.com/' then 'skipped: not a Slack link'
                        when (f->>'size')::bigint > ? then 'too_large'
                        else 'to_copy'
                    end as category
                from slack_messages m
                join slack_channels c on c.id = m.slack_channel_id and c.id = any(?::int[])
                cross join jsonb_array_elements(case when jsonb_typeof(m.data->'files') = 'array' then m.data->'files' else '[]'::jsonb end) f
                where m.deleted_at is null and f->>'id' is not null
                  and not exists (select 1 from slack_files s where s.slack_id = f->>'id' and s.is_private = ?::boolean and s.state <> all(?::text[]))
                order by f->>'id', m.id
            ) files group by category order by category",
            [CopySlackMessageFile::maxBytes(), '{'.implode(',', $channelIds).'}', $private ? 'true' : 'false', '{'.implode(',', SlackFileStateEnum::toCopy($retryFailed)).'}']
        );

        return collect($rows)->mapWithKeys(fn ($row) => [$row->category => ['files' => (int) $row->files, 'bytes' => (int) $row->bytes]])->all();
    }

    public function asCommand(Command $command): int
    {
        Nightwatch::dontSample();

        $channel = null;
        if ($slackChannel = $command->option('channel')) {
            $channel = SlackChannel::where('slack_id', $slackChannel)->orWhere('name', $slackChannel)->first();
            if (!$channel) {
                $command->error("No Slack channel $slackChannel");

                return 1;
            }
        }

        if ($command->option('dry-run')) {
            $command->table(
                ['What would happen', 'Files', 'Size (MB)'],
                collect($this->dryRun($channel, (bool) $command->option('retry-failed'), (bool) $command->option('private')))->map(fn ($row, $category) => [$category, $row['files'], round($row['bytes'] / 1048576, 1)])->values()->all()
            );

            return 0;
        }

        $limit  = $command->option('limit');
        $totals = $this->handle($channel, $limit !== null ? (int) $limit : null, (float) $command->option('pause'), (bool) $command->option('retry-failed'), (bool) $command->option('private'));
        $command->info("Downloads queued: {$totals['queued']}, recorded without download: {$totals['recorded']}");

        return 0;
    }
}
