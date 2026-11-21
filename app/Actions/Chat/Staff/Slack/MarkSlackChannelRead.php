<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 21 Nov 2026 19:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Chat\Staff\Slack;

use App\Models\Chat\SlackChannel;
use App\Models\SysAdmin\User;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * A person's read position in a Slack channel, kept by aiku and independent of Slack's own. It only moves forward.
 */
class MarkSlackChannelRead
{
    use AsAction;

    private const string NOW_TS = "to_char(extract(epoch FROM now()), 'FM9999999999.000000')";

    public function authorize(ActionRequest $request): bool
    {
        return $request->route('slackChannel')->isVisibleTo($request->user());
    }

    public function rules(): array
    {
        return [
            'ts' => ['sometimes', 'nullable', 'string', 'regex:/^\d{10}\.\d{6}$/'],
        ];
    }

    public function handle(User $user, SlackChannel $slackChannel, ?string $ts = null): void
    {
        if ($ts === null) {
            $this->upToNow($user, [$slackChannel->id]);

            return;
        }

        DB::statement(
            "INSERT INTO slack_channel_reads (user_id, slack_channel_id, last_read_ts, created_at, updated_at)
            SELECT ?, ?, least(?, coalesce((SELECT m.ts FROM slack_messages m WHERE m.slack_channel_id = ? ORDER BY m.ts DESC LIMIT 1), ".self::NOW_TS.')), now(), now()
            ON CONFLICT (user_id, slack_channel_id) DO UPDATE SET last_read_ts = greatest(slack_channel_reads.last_read_ts, excluded.last_read_ts), updated_at = now()',
            [$user->id, $slackChannel->id, $ts, $slackChannel->id]
        );
    }

    /**
     * Read up to the newest message of each channel, in one statement. $onlyMissing gives a channel seen for the
     * first time a position, so it opens with nothing unread instead of its whole history.
     *
     * @param  list<int>  $slackChannelIds
     */
    public function upToNow(User $user, array $slackChannelIds, bool $onlyMissing = false): void
    {
        if ($slackChannelIds === []) {
            return;
        }

        $placeholders = implode(',', array_fill(0, count($slackChannelIds), '?'));
        $onConflict   = $onlyMissing
            ? 'DO NOTHING'
            : 'DO UPDATE SET last_read_ts = greatest(slack_channel_reads.last_read_ts, excluded.last_read_ts), updated_at = now()';

        DB::statement(
            "INSERT INTO slack_channel_reads (user_id, slack_channel_id, last_read_ts, created_at, updated_at)
            SELECT ?, c.id, coalesce((SELECT m.ts FROM slack_messages m WHERE m.slack_channel_id = c.id ORDER BY m.ts DESC LIMIT 1), ".self::NOW_TS."), now(), now()
            FROM slack_channels c WHERE c.id IN ($placeholders)
            ON CONFLICT (user_id, slack_channel_id) $onConflict",
            [$user->id, ...$slackChannelIds]
        );
    }

    public function asController(SlackChannel $slackChannel, ActionRequest $request): array
    {
        $this->handle($request->user(), $slackChannel, $request->validated('ts'));

        return ['ok' => true];
    }

    public function inAllChannels(ActionRequest $request): array
    {
        $this->upToNow($request->user(), SlackChannel::visibleTo($request->user())->pluck('id')->all());

        return ['ok' => true];
    }
}
