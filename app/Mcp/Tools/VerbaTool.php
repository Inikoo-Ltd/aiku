<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 28 Nov 2026 20:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Mcp\Tools;

use App\Actions\Chat\Staff\Json\GetSlackThread;
use App\Actions\Chat\Staff\Json\GetStaffConversations;
use App\Actions\Chat\Staff\Slack\FormatSlackMessages;
use App\Actions\Chat\Staff\Slack\GetSlackChannelMessages;
use App\Actions\Chat\Staff\UI\ShowStaffSlackChannels;
use App\Models\Chat\SlackChannel;
use App\Models\Chat\StaffConversation;
use App\Models\Chat\StaffMessage;
use App\Models\SysAdmin\User;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Arr;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

/**
 * Reads nothing a person could not read in Verba themselves, and never moves their read position: an assistant reading is
 * not the person reading.
 */
#[Description('Reads the user\'s own Verba (staff chat, /vx). Without conversation: lists their Verba conversations (V·Ds and private V·Desks they are in, with unread counts), the public V·Desks (Slack channels) they may read, the private Slack V·Desks they are a member of and their Slack DMs, each with an id. With conversation (an id from that list): the latest messages, oldest first; before (a message id for Verba, a Slack ts for V·Desks) pages back, and thread_ts opens the replies of a Slack thread. Only conversations this user is in, and channels they may read in Verba; never anyone else\'s. Does not mark anything read. Read only.')]
#[IsReadOnly]
class VerbaTool extends Tool
{
    use WithVerbaConversation;

    public const int LIMIT = 50;

    public function handle(Request $request): Response
    {
        $request->validate([
            'conversation' => ['sometimes', 'nullable', 'string', 'max:40'],
            'before'       => ['sometimes', 'nullable', 'string', 'max:30'],
            'thread_ts'    => ['sometimes', 'nullable', 'string', 'regex:/^\d{1,12}\.\d{1,8}$/'],
            'limit'        => ['sometimes', 'integer', 'min:1', 'max:'.self::LIMIT],
        ]);

        /** @var User $user */
        $user = $request->user();

        if (!$request->filled('conversation')) {
            return Response::json($this->lists($user));
        }

        $conversation = $this->verbaConversation($user, (string) $request->string('conversation'));
        if (!$conversation) {
            return Response::error(self::NOT_FOUND);
        }

        $limit = $request->integer('limit', self::LIMIT);

        return $conversation instanceof StaffConversation
            ? Response::json($this->staffMessages($conversation, $user, $request->get('before'), $limit))
            : $this->slackMessages($conversation, $user, $request->get('before'), $request->get('thread_ts'), $limit);
    }

    /**
     * @return array<string, mixed>
     */
    private function lists(User $user): array
    {
        $starred = (array) (Arr::get($user->settings, 'desk_starred') ?? []);
        $slack   = fn (SlackChannel $channel, ?string $name = null) => array_filter([
            'id'          => self::verbaId($channel),
            'name'        => $name ?? $channel->name,
            'archived'    => $channel->is_archived ?: null,
            'starred'     => in_array('slack-'.$channel->id, $starred, true) ?: null,
        ], fn ($value) => $value !== null);

        return [
            'conversations'      => GetStaffConversations::run($user)->map(fn (StaffConversation $conversation) => array_filter([
                'id'              => $conversation->ulid,
                'type'            => $conversation->isVerbration() ? 'private_v_desk' : $conversation->type,
                'name'            => $conversation->name ?: $conversation->participants->where('id', '!=', $user->id)->map(fn (User $participant) => $participant->chatName())->implode(', '),
                'about'           => $conversation->context_type,
                'last_message_at' => $conversation->last_message_at?->toIso8601String(),
                'unread'          => (int) $conversation->unread_count ?: null,
                'starred'         => in_array('dm-'.$conversation->ulid, $starred, true) ?: null,
            ], fn ($value) => $value !== null))->values()->all(),
            'public_v_desks'     => SlackChannel::visibleTo($user)->orderBy('name')->get(['id', 'name', 'is_archived', 'slack_team_id'])->map(fn (SlackChannel $channel) => $slack($channel))->all(),
            'private_slack_v_desks' => SlackChannel::privateVisibleTo($user)->orderBy('name')->get(['id', 'name', 'is_archived', 'slack_team_id'])->map(fn (SlackChannel $channel) => $slack($channel))->all(),
            'slack_dms'          => SlackChannel::directVisibleTo($user)->get(['id', 'name', 'is_archived', 'slack_team_id', 'direct_type'])->map(fn (SlackChannel $channel) => $slack($channel, ShowStaffSlackChannels::directName($channel, $user)))->all(),
        ];
    }

    /**
     * Someone who left keeps the history up to that moment, as in Verba (GetStaffMessages).
     *
     * @return array<string, mixed>
     */
    private function staffMessages(StaffConversation $conversation, User $user, ?string $before, int $limit): array
    {
        $leftAt = $conversation->leftAtFor($user);

        $messages = $conversation->messages()
            ->with('user')
            ->when($leftAt, fn ($query) => $query->where('created_at', '<=', $leftAt))
            ->when(ctype_digit((string) $before), fn ($query) => $query->where('id', '<', (int) $before))
            ->latest('id')
            ->limit($limit)
            ->get()
            ->reverse()
            ->values();

        return [
            'conversation' => $conversation->ulid,
            'name'         => $conversation->name,
            'can_write'    => !$leftAt,
            'messages'     => $messages->map(fn (StaffMessage $message) => array_filter([
                'id'        => $message->id,
                'from'      => $message->user?->username,
                'name'      => $message->user?->chatName(),
                'at'        => $message->created_at?->toIso8601String(),
                'reply_to'  => $message->parent_id,
                'body'      => $message->body,
                'has_image' => $message->media_id ? true : null,
            ], fn ($value) => $value !== null && $value !== ''))->all(),
            'older'        => $messages->count() === $limit ? 'pass before='.$messages->first()?->id.' for older messages' : null,
        ];
    }

    private function slackMessages(SlackChannel $channel, User $user, ?string $before, ?string $threadTs, int $limit): Response
    {
        $action = GetSlackChannelMessages::make();

        if ($threadTs) {
            try {
                $thread = GetSlackThread::make()->handle($channel, $threadTs, $limit);
            } catch (ModelNotFoundException) {
                return Response::error('No thread '.$threadTs.' in this V·Desk.');
            }
            $rows = array_values(array_filter([$thread['parent'], ...$thread['replies']]));
        } else {
            $messages = GetSlackChannelMessages::streamQuery($channel)
                ->with(GetSlackChannelMessages::relations())
                ->when($before && preg_match('/^\d{1,12}\.\d{1,8}$/', $before), fn ($query) => $query->where('ts', '<', $before))
                ->orderByDesc('ts')
                ->limit($limit)
                ->get(GetSlackChannelMessages::COLUMNS)
                ->reverse()
                ->values();
            $rows = $action->rows($channel, $messages, $user);
        }

        return Response::json([
            'conversation' => self::verbaId($channel),
            'name'         => $channel->direct_type ? ShowStaffSlackChannels::directName($channel, $user) : $channel->name,
            'kind'         => $channel->direct_type ? 'slack_dm' : ($channel->is_private ? 'private_v_desk' : 'public_v_desk'),
            'messages'     => array_map(fn (array $row) => array_filter([
                'ts'          => $row['ts'],
                'thread_ts'   => $row['thread_ts'] !== $row['ts'] ? $row['thread_ts'] : null,
                'from'        => $row['author']['name'] ?? null,
                'at'          => $row['sent_at'],
                'text'        => !empty($row['is_deleted']) ? null : trim(FormatSlackMessages::plainText($row['nodes'] ?? [])),
                'deleted'     => !empty($row['is_deleted']) ?: null,
                'not_brought' => !empty($row['is_gap']) ? 'its author has not connected Slack to Verba, so the words are not in aiku' : null,
                'replies'     => ($row['reply_count'] ?? 0) ?: null,
                'files'       => count($row['files'] ?? []) ?: null,
            ], fn ($value) => $value !== null && $value !== ''), $rows),
            'older'        => !$threadTs && count($rows) === $limit ? 'pass before='.$rows[0]['ts'].' for older messages' : null,
        ]);
    }

    /**
     * @return array<string, JsonSchema>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'conversation' => $schema->string()->description('Id from the list: a Verba conversation id (26 characters) or slack-<number> for a V·Desk or Slack DM; omit to list them'),
            'before'       => $schema->string()->description('Page back: a message id (Verba) or a Slack ts (V·Desks) from the previous answer'),
            'thread_ts'    => $schema->string()->description('Slack only: the ts of a thread parent, to read its replies'),
            'limit'        => $schema->integer()->description('Messages to return, up to 50 (default 50)'),
        ];
    }
}
