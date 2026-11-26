<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 26 Nov 2026 23:30:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Chat\Staff\Slack;

use App\Models\Chat\SlackChannel;
use App\Models\Chat\SlackMessage;
use App\Models\Chat\SlackMessageVersion;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Switching private conversations off (INI-431): the person's own words in DMs and group DMs are truly deleted and become gaps,
 * deleted messages and earlier versions included. The one exception to "deleting only hides". Never called on deactivation:
 * a leaver's words stay for the retention period. Private channels stay, they belong to all their members.
 */
class PurgeSlackDirectWords
{
    use AsAction;

    public function handle(string $slackUserId): int
    {
        $messages = SlackMessage::withTrashed()
            ->whereIn('slack_channel_id', SlackChannel::whereNotNull('direct_type')->select('id'))
            ->where('slack_user_id', $slackUserId);

        return DB::transaction(function () use ($messages) {
            SlackMessageVersion::whereIn('slack_message_id', (clone $messages)->select('id'))->delete();

            return (clone $messages)->toBase()->update([
                'text'          => null,
                'data'          => null,
                'subtype'       => null,
                'edited_at'     => null,
                'search_vector' => null,
                'updated_at'    => now(),
            ]);
        });
    }
}
