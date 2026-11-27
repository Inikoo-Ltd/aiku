<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 26 Nov 2026 12:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Chat\Staff\Slack\Write;

use App\Actions\Chat\Staff\Slack\SyncSlackPinsAndBookmarks;
use App\Models\Chat\SlackChannel;
use App\Models\Chat\SlackChannelBookmark;
use App\Models\SysAdmin\User;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Removes a channel bookmark in Slack with the person's own token and marks it removed as the nightly sync would;
 * a bookmark already gone in Slack is removed here too.
 */
class RemoveSlackChannelBookmark
{
    use AsAction;
    use WithSlackBookmarkWrite;

    /**
     * @return array{bookmarks: list<array<string, mixed>>}
     */
    public function handle(User $user, SlackChannel $slackChannel, SlackChannelBookmark $bookmark): array
    {
        $this->ensureMayWrite($user, $slackChannel);
        $bookmark = $this->liveBookmark($slackChannel, $bookmark->id);
        $client   = $this->clientFor($user, channel: $slackChannel);

        $this->callSlack($client, 'bookmarks.remove', ['channel_id' => $slackChannel->slack_id, 'bookmark_id' => $bookmark->slack_id], ['bookmark_not_found']);
        SyncSlackPinsAndBookmarks::make()->removeBookmark($bookmark);

        return $this->bookmarksOf($slackChannel);
    }

    /**
     * @return array{bookmarks: list<array<string, mixed>>}
     */
    public function asController(SlackChannel $slackChannel, SlackChannelBookmark $bookmark, ActionRequest $request): array
    {
        return $this->handle($request->user(), $slackChannel, $bookmark);
    }
}
