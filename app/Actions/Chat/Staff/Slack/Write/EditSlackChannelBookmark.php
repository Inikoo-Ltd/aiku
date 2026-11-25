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
use Illuminate\Support\Arr;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Changes a channel bookmark in Slack with the person's own token and keeps Slack's answer through the code the
 * nightly bookmark sync uses.
 */
class EditSlackChannelBookmark
{
    use AsAction;
    use WithSlackBookmarkWrite;

    public function rules(): array
    {
        return $this->bookmarkRules();
    }

    /**
     * @param  array{title: string, link: string, emoji?: string|null}  $attributes
     * @return array{bookmarks: list<array<string, mixed>>}
     */
    public function handle(User $user, SlackChannel $slackChannel, SlackChannelBookmark $bookmark, array $attributes): array
    {
        $this->ensureMayWrite($user, $slackChannel);
        $bookmark = $this->liveBookmark($slackChannel, $bookmark->id);
        $client   = $this->clientFor($user);

        $answer = $this->callSlack($client, 'bookmarks.edit', [
            'channel_id'  => $slackChannel->slack_id,
            'bookmark_id' => $bookmark->slack_id,
            'title'       => $attributes['title'],
            'link'        => $attributes['link'],
            'emoji'       => $this->emojiTag($attributes['emoji'] ?? null),
        ]);

        $edited = (array) Arr::get($answer, 'bookmark', []);
        if (Arr::get($edited, 'id') === $bookmark->slack_id) {
            SyncSlackPinsAndBookmarks::make()->storeBookmark($slackChannel, $edited);
        }

        return $this->bookmarksOf($slackChannel);
    }

    /**
     * @return array{bookmarks: list<array<string, mixed>>}
     */
    public function asController(SlackChannel $slackChannel, SlackChannelBookmark $bookmark, ActionRequest $request): array
    {
        return $this->handle($request->user(), $slackChannel, $bookmark, $request->validated());
    }
}
