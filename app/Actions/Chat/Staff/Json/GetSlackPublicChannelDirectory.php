<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 28 Nov 2026 20:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Chat\Staff\Json;

use App\Models\Chat\SlackChannel;
use App\Models\SysAdmin\User;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Browse V·Desks (INI-467): every open public channel the person may read, with its member count and whether they are in
 * it in Slack, so they can join from aiku. Loaded only when the dialog opens.
 */
class GetSlackPublicChannelDirectory
{
    use AsAction;

    public function authorize(ActionRequest $request): bool
    {
        return SlackChannel::userMayReadPublicChannels($request->user());
    }

    /**
     * @return list<array{id: int, name: string|null, topic: string|null, member_count: int, is_mine: bool}>
     */
    public function handle(User $user): array
    {
        $mine = SlackChannel::linkedSlackIds($user);

        return SlackChannel::visibleTo($user)
            ->where('is_archived', false)
            ->withCount('members')
            ->withExists(['members as is_mine' => fn ($query) => $query->whereIn('slack_user_id', $mine)])
            ->orderBy('name')
            ->get(['id', 'name', 'data', 'slack_team_id'])
            ->map(fn (SlackChannel $channel) => [
                'id'           => $channel->id,
                'name'         => $channel->name,
                'topic'        => ($channel->data['purpose']['value'] ?? null) ?: ($channel->data['topic']['value'] ?? null) ?: null,
                'member_count' => (int) $channel->members_count,
                'is_mine'      => (bool) $channel->is_mine,
            ])
            ->all();
    }

    /**
     * @return array{data: list<array<string, mixed>>}
     */
    public function asController(ActionRequest $request): array
    {
        return ['data' => $this->handle($request->user())];
    }
}
