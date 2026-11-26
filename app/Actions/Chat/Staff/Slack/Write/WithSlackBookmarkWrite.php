<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 26 Nov 2026 12:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Chat\Staff\Slack\Write;

use App\Actions\Chat\Staff\UI\ShowStaffSlackChannels;
use App\Models\Chat\SlackChannel;
use App\Models\Chat\SlackChannelBookmark;

/**
 * What adding, editing and removing a channel bookmark from aiku share. Slack has no call to reorder bookmarks, so
 * none is offered.
 */
trait WithSlackBookmarkWrite
{
    use WithSlackMessageWrite;

    /**
     * @return array<string, array<int, string>>
     */
    protected function bookmarkRules(): array
    {
        return [
            'title' => ['required', 'string', 'max:200'],
            'link'  => ['required', 'string', 'max:2000', 'regex:/^https?:\/\/[^\s"<>\\\\`]+$/i'],
            'emoji' => ['nullable', 'string', 'max:100', "regex:/^:?[a-z0-9_+'-]+(::skin-tone-[2-6])?:?$/"],
        ];
    }

    /**
     * Slack wants the emoji as :name:.
     */
    protected function emojiTag(?string $emoji): ?string
    {
        return $emoji ? ':'.trim($emoji, ':').':' : null;
    }

    protected function liveBookmark(SlackChannel $slackChannel, int $id): SlackChannelBookmark
    {
        return $slackChannel->bookmarks()->whereNull('removed_in_slack_at')->findOrFail($id);
    }

    /**
     * @return array{bookmarks: list<array<string, mixed>>}
     */
    protected function bookmarksOf(SlackChannel $slackChannel): array
    {
        return ['bookmarks' => ShowStaffSlackChannels::make()->bookmarks($slackChannel)];
    }
}
