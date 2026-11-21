<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 21 Nov 2026 19:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Chat\Staff\Json;

use App\Actions\Chat\Staff\Slack\GetSlackChannelMessages;
use App\Models\Chat\SlackChannel;
use App\Models\Chat\SlackMessage;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * The pinned messages of a Slack channel, most recently pinned first, shaped like the stream.
 */
class GetSlackChannelPins
{
    use AsAction;

    public const int LIMIT = 100;

    public function authorize(ActionRequest $request): bool
    {
        return $request->route('slackChannel')->isVisibleTo($request->user());
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function handle(SlackChannel $slackChannel): array
    {
        $messages = SlackMessage::where('slack_channel_id', $slackChannel->id)
            ->whereNotNull('pinned_at')
            ->with(GetSlackChannelMessages::relations())
            ->orderByDesc('pinned_at')
            ->limit(self::LIMIT)
            ->get(GetSlackChannelMessages::COLUMNS);

        return GetSlackChannelMessages::make()->rows($slackChannel, $messages);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function asController(SlackChannel $slackChannel): array
    {
        return $this->handle($slackChannel);
    }
}
