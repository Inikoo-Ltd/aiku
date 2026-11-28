<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 22 Aug 2026 14:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Chat\Staff;

use App\Http\Resources\Chat\StaffConversationResource;
use App\Actions\Chat\Staff\Slack\StoreSlackDirectMessage;
use App\Actions\Chat\Staff\Slack\Write\SlackWriteAccess;
use App\Actions\Chat\Staff\Slack\Write\SlackWriteClient;
use App\Actions\Chat\Staff\Slack\Write\SlackWriteException;
use App\Models\Chat\SlackChannel;
use App\Models\Chat\SlackConnection;
use App\Models\Chat\StaffConversation;
use App\Models\SysAdmin\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;

class StoreStaffConversation
{
    use AsAction;

    public const int MAX_SLACK_DIRECT_PEOPLE = 8;

    public const int SLACK_OPENS_PER_MINUTE = 20;

    /**
     * @param array{user_ids: int[], name?: string, context_type?: string, context_id?: int} $modelData
     */
    public function handle(User $creator, array $modelData): StaffConversation
    {
        $userIds = array_values(array_unique(array_merge([$creator->id], $modelData['user_ids'])));
        $isDm    = count($userIds) === 2 && empty($modelData['context_type']) && empty($modelData['name']);

        $dmKey = $isDm ? StaffConversation::dmKey($userIds) : null;

        if ($dmKey && ($existing = StaffConversation::where('dm_key', $dmKey)->first())) {
            return $existing;
        }

        try {
            return DB::transaction(function () use ($creator, $modelData, $userIds, $isDm, $dmKey) {
                $conversation = StaffConversation::create([
                    'group_id'           => $creator->group_id,
                    'type'               => $isDm ? 'dm' : 'group',
                    'name'               => $modelData['name'] ?? null,
                    'dm_key'             => $dmKey,
                    'context_type'       => $modelData['context_type'] ?? null,
                    'context_id'         => $modelData['context_id'] ?? null,
                    'created_by_user_id' => $creator->id,
                ]);
                $conversation->participants()->attach($userIds);

                return $conversation;
            });
        } catch (UniqueConstraintViolationException) {
            return StaffConversation::where('dm_key', $dmKey)->firstOrFail();
        }
    }

    /**
     * The Slack DM or group DM to open instead of a Verba chat (INI-593): a plain chat (no name, no context) of up to
     * MAX_SLACK_DIRECT_PEOPLE others, each linked to a Slack person, and the starter opted in to private conversations and able
     * to write there now. The others need not have opted in: they answer in Slack, and in Verba their words show as gaps
     * until they do (INI-431).
     * A one-to-one DM already in Verba is used as it is; otherwise Slack is asked with the starter's own token
     * (conversations.open), which gives back the existing DM or group DM of exactly these people, or starts it. It wins over
     * a Verba chat the same people may already have, so the conversation goes on in one place. When Slack cannot be
     * reached the chat is a Verba one.
     *
     * @param array{user_ids: int[], name?: string, context_type?: string, context_id?: int} $modelData
     */
    public static function slackDirectInstead(User $creator, array $modelData): ?SlackChannel
    {
        $otherIds = array_values(array_diff(array_unique(array_map('intval', $modelData['user_ids'])), [$creator->id]));
        if ($otherIds === [] || count($otherIds) > self::MAX_SLACK_DIRECT_PEOPLE || !empty($modelData['context_type']) || !empty($modelData['name'])
            || SlackWriteAccess::directReasonUnavailable($creator) !== null) {
            return null;
        }
        $mine = SlackConnection::main()->where('user_id', $creator->id)->value('slack_user_id');
        if (!$mine || SlackChannel::privateReader($creator)?->slack_id !== $mine) {
            return null;
        }
        $theirs = User::where('group_id', $creator->group_id)->whereIn('id', $otherIds)->get()
            ->map(fn (User $other) => SlackChannel::linkedSlackPerson($other)?->slack_id)
            ->filter(fn (?string $slackId) => $slackId && $slackId !== $mine)
            ->unique()->values()->all();
        if (count($theirs) !== count($otherIds)) {
            return null;
        }

        if (count($theirs) === 1) {
            $known = SlackChannel::directVisibleTo($creator)->where('direct_type', 'im')->where('is_archived', false)
                ->whereHas('members', fn ($query) => $query->where('slack_user_id', $theirs[0]))
                ->orderByDesc('id')->first();
            if ($known) {
                return $known;
            }
        }

        return self::openInSlack($creator, $mine, $theirs);
    }

    /**
     * @param  list<string>  $theirs
     */
    private static function openInSlack(User $creator, string $mine, array $theirs): ?SlackChannel
    {
        $client = SlackWriteClient::for($creator);
        if (!$client || !RateLimiter::attempt('slack-direct-open:'.$creator->id, self::SLACK_OPENS_PER_MINUTE, fn () => true)) {
            return null;
        }
        try {
            $slackId = Arr::get($client->call('conversations.open', ['users' => implode(',', $theirs)]), 'channel.id');
        } catch (SlackWriteException) {
            return null;
        }
        if (!is_string($slackId) || $slackId === '') {
            return null;
        }

        try {
            $channel = SlackChannel::firstOrCreate(
                ['slack_id' => $slackId],
                ['group_id' => $creator->group_id, 'direct_type' => count($theirs) === 1 ? 'im' : 'mpim', 'is_private' => true, 'is_public' => false, 'is_member' => false]
            );
        } catch (UniqueConstraintViolationException) {
            $channel = SlackChannel::where('slack_id', $slackId)->firstOrFail();
        }
        if (!SlackChannel::keptDirect()->whereKey($channel->id)->exists() || $channel->is_archived) {
            return null;
        }
        StoreSlackDirectMessage::make()->addMembers($channel, [$mine, ...$theirs]);

        return $channel;
    }

    public function rules(): array
    {
        return [
            'user_ids'     => ['required', 'array', 'min:1', function (string $attribute, mixed $value, \Closure $fail): void {
                if (collect($value)->map(fn ($userId) => (int) $userId)->reject(fn (int $userId) => $userId === request()->user()->id)->isEmpty()) {
                    $fail(__('You cannot start a conversation with only yourself.'));
                }
            }],
            'user_ids.*'   => ['integer', Rule::exists('users', 'id')->where('group_id', request()->user()->group_id)],
            'name'         => ['sometimes', 'nullable', 'string', 'max:255'],
            'prefer_slack' => ['sometimes', 'boolean'],
        ];
    }

    public function asController(ActionRequest $request): StaffConversationResource|JsonResponse
    {
        $slackDirect = $request->boolean('prefer_slack') ? self::slackDirectInstead($request->user(), $request->validated()) : null;
        if ($slackDirect) {
            return response()->json(['slack_direct' => ['id' => $slackDirect->id, 'url' => route('grp.chat.staff.direct.slack.show', $slackDirect->id)]]);
        }

        $conversation = $this->handle($request->user(), $request->validated());
        $conversation->load('participants');

        return new StaffConversationResource($conversation);
    }
}
