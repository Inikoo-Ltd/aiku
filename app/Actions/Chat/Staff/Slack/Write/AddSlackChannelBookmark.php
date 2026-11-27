<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 26 Nov 2026 12:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Chat\Staff\Slack\Write;

use App\Actions\Chat\Staff\Slack\SyncSlackPinsAndBookmarks;
use App\Models\Chat\SlackChannel;
use App\Models\SysAdmin\User;
use Illuminate\Support\Arr;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Adds a link bookmark to the channel in Slack with the person's own token and keeps Slack's answer through the code
 * the nightly bookmark sync uses.
 */
class AddSlackChannelBookmark
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
    public function handle(User $user, SlackChannel $slackChannel, array $attributes): array
    {
        $this->ensureMayWrite($user, $slackChannel);
        $client = $this->clientFor($user, channel: $slackChannel);

        $answer = $this->callSlack($client, 'bookmarks.add', [
            'channel_id' => $slackChannel->slack_id,
            'title'      => $attributes['title'],
            'type'       => 'link',
            'link'       => $attributes['link'],
            'emoji'      => $this->emojiTag($attributes['emoji'] ?? null),
        ]);

        $bookmark = (array) Arr::get($answer, 'bookmark', []);
        if (Arr::get($bookmark, 'id')) {
            SyncSlackPinsAndBookmarks::make()->storeBookmark($slackChannel, $bookmark);
        }

        return $this->bookmarksOf($slackChannel);
    }

    /**
     * @return array{bookmarks: list<array<string, mixed>>}
     */
    public function asController(SlackChannel $slackChannel, ActionRequest $request): array
    {
        return $this->handle($request->user(), $slackChannel, $request->validated());
    }
}
