<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 28 Nov 2026 20:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Chat\Staff\Slack\Write;

use App\Actions\Chat\Staff\Slack\UpdateSlackChannelFromEvent;
use App\Actions\Chat\Staff\Slack\UpdateSlackChannelMember;
use App\Models\Chat\SlackChannel;
use App\Models\SysAdmin\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Arr;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;
use Throwable;

/**
 * New public V·Desk (INI-467): a Slack public channel of the main workspace created with the person's own token, never
 * private, stored at once through the code the channel events use, so Slack's echo changes nothing. The aiku bot joins
 * it there and then, or its messages would not be kept; general auto-join of channels made in Slack stays undecided (INI-420).
 */
class StoreSlackPublicChannel
{
    use AsAction;
    use WithSlackChannelWrite;

    private ?string $warning = null;

    public function authorize(ActionRequest $request): bool
    {
        return SlackWriteAccess::allows($request->user());
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name'        => self::nameRules(),
            'description' => ['nullable', 'string', 'max:250'],
        ];
    }

    public function asController(ActionRequest $request): JsonResponse
    {
        $channel = $this->handle($request->user(), $request->validated('name'), $request->validated('description'));

        return response()->json(['id' => $channel->id, 'url' => route('grp.chat.staff.slack.show', $channel->id), 'warning' => $this->warning]);
    }

    public function handle(User $user, string $name, ?string $description = null): SlackChannel
    {
        $client = $this->clientFor($user);

        $slackChannel = (array) Arr::get($this->callChannel($client, 'conversations.create', ['name' => $name, 'is_private' => false]), 'channel', []);
        if (!Arr::get($slackChannel, 'id')) {
            $this->refuse(self::sentenceFor(null));
        }
        $channel = UpdateSlackChannelFromEvent::make()->handle(['type' => 'channel_created', 'channel' => $slackChannel], null);
        $channel->update(['data' => $slackChannel, 'history_synced_at' => now()]);
        UpdateSlackChannelMember::make()->handle(['type' => 'member_joined_channel', 'user' => $client->slackUserId(), 'channel' => $channel->slack_id]);

        try {
            UpdateSlackChannelFromEvent::make()->joinNewPublicChannel($channel);
        } catch (Throwable $exception) {
            report($exception);
        }

        if ($description) {
            try {
                $this->keepChannelData($channel, $client->call('conversations.setPurpose', ['channel' => $channel->slack_id, 'purpose' => $description]), 'purpose', $description);
            } catch (SlackWriteException) {
                $this->warning = __('The V·Desk was created, but Slack did not save its description. Add it from the channel menu');
            }
        }

        return $channel;
    }
}
