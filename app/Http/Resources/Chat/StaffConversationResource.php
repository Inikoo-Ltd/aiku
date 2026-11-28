<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 22 Aug 2026 14:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Http\Resources\Chat;

use App\Actions\Chat\Staff\StaffChatWallpaper;
use App\Enums\Tasks\StaffTaskStatusEnum;
use App\Models\Analytics\UserRequest;
use App\Models\Chat\StaffConversation;
use App\Models\SysAdmin\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin StaffConversation
 */
class StaffConversationResource extends JsonResource
{
    /** The conversation list sends each person's avatar once beside the list instead of in every conversation they are in. */
    public bool $avatarsApart = false;

    protected function contextUrl(): ?string
    {
        return self::urlFor($this->context_type, $this->context);
    }

    public static function urlFor(?string $type, mixed $context): ?string
    {
        if (!$context) {
            return null;
        }

        return match ($type) {
            'DeliveryNote' => route('grp.org.warehouses.show.dispatching.delivery_notes.show', [$context->organisation->slug, $context->warehouse->slug, $context->slug]),
            'Order'        => route('grp.org.shops.show.ordering.orders.show', [$context->organisation->slug, $context->shop->slug, $context->slug]),
            'StaffTask'    => route('grp.tasks.show', $context->reference),
            'ChatSession'  => route('grp.org.chat.conversations.detail', ['organisation' => $context->shop?->organisation?->slug, 'chatSession' => $context->id]),
            'PickingSession' => route('grp.org.warehouses.show.dispatching.picking_sessions.show', [$context->organisation->slug, $context->warehouse->slug, $context->slug]),
            default        => null,
        };
    }

    /**
     * @return array{reference: string, is_open: bool, requester_id: int, assignee_id: int|null, collaborator_ids: int[], subtasks: array<int, array{title: string, status: string}>}|null
     */
    protected function task(): ?array
    {
        if ($this->context_type !== 'StaffTask' || !$this->context) {
            return null;
        }

        $task = $this->context;

        return [
            'reference'        => $task->reference,
            'is_open'          => $task->isOpen(),
            'status'           => $task->status->value,
            'status_label'     => StaffTaskStatusEnum::labels()[$task->status->value],
            'status_icon'      => StaffTaskStatusEnum::stateIcon()[$task->status->value],
            'requester_id'     => $task->requester_id,
            'assignee_id'      => $task->assignee_id,
            'collaborator_ids' => $task->collaborators->pluck('id')->all(),
            'subtasks'         => $task->data['subtasks'] ?? [],
            'description'      => $task->description,
            'model_label'      => $task->model?->reference ?? $task->model?->code ?? $task->model?->name,
            'model_url'        => self::urlFor($task->model_type, $task->model),
        ];
    }

    /**
     * An older Verba DM between two people who also have a Slack DM in Verba points to it (INI-593), so they carry on there.
     * The viewer's Slack DMs are read once per request: other person's aiku user id => Slack DM id.
     */
    protected function slackDirectUrl(?User $viewer, \Illuminate\Http\Request $request): ?string
    {
        if (!$viewer || $this->type !== 'dm') {
            return null;
        }
        $key = 'staff-chat-slack-direct-map:'.$viewer->id;
        if (!$request->attributes->has($key)) {
            $request->attributes->set($key, self::slackDirectMap($viewer));
        }
        $other = $this->participants->first(fn (User $user) => $user->id !== $viewer->id);
        $id    = $other ? ($request->attributes->get($key)[$other->id] ?? null) : null;

        return $id ? route('grp.chat.staff.direct.slack.show', $id) : null;
    }

    /**
     * @return array<int, int>
     */
    public static function slackDirectMap(User $viewer): array
    {
        $ims = \App\Models\Chat\SlackChannel::directVisibleTo($viewer)->where('direct_type', 'im')->where('is_archived', false)->pluck('id');
        if ($ims->isEmpty()) {
            return [];
        }

        return \App\Models\Chat\SlackChannelMember::query()
            ->join('slack_users', 'slack_users.slack_id', '=', 'slack_channel_members.slack_user_id')
            ->whereIn('slack_channel_members.slack_channel_id', $ims)
            ->whereNotIn('slack_channel_members.slack_user_id', \App\Models\Chat\SlackChannel::linkedSlackIds($viewer))
            ->whereNotNull('slack_users.user_id')
            ->where('slack_users.user_id', '!=', $viewer->id)
            ->orderBy('slack_channel_members.slack_channel_id')
            ->pluck('slack_channel_members.slack_channel_id', 'slack_users.user_id')
            ->all();
    }

    public function toArray($request): array
    {
        $viewer       = $request->user();
        $myPivot      = $this->participants->firstWhere('id', $viewer?->id)?->pivot;
        $myWallpaper  = $myPivot?->wallpaper ? json_decode($myPivot->wallpaper, true) : null;
        $myLeftAt     = $myPivot?->left_at;
        $participants = $this->participants->filter(fn (User $user) => !$user->pivot?->left_at)->map(fn (User $user) => [
            'id'     => $user->id,
            'name'   => $user->chatName(),
            'handle' => $user->nickname ?: $user->username,
            ...($this->avatarsApart ? [] : ['avatar' => $user->image_id ? User::chatAvatarOf($user->imageSources(0, 48)) : null]),
            'last_seen_at' => Cache::remember('staff-last-seen:'.$user->id, 120, fn () => UserRequest::where('user_id', $user->id)->max('date')),
            'last_read_at' => $user->pivot?->last_read_at ? Carbon::parse($user->pivot->last_read_at)->toIso8601ZuluString('microsecond') : null,
            'last_delivered_at' => $user->pivot?->last_delivered_at ? Carbon::parse($user->pivot->last_delivered_at)->toIso8601ZuluString('microsecond') : null,
        ])->values();

        return [
            'ulid'            => $this->ulid,
            'type'            => $this->type,
            'name'            => $this->name,
            'context_type'    => $this->context_type,
            'context_id'      => $this->context_id,
            'context_label'   => $this->context?->reference ?? $this->name,
            'context_url'     => $this->contextUrl(),
            'task'            => $this->task(),
            'participants'    => $participants,
            'my_left_at'      => $myLeftAt ? Carbon::parse($myLeftAt)->toIso8601ZuluString() : null,
            'is_watching'     => (bool) ($myPivot?->is_watching ?? false),
            'wallpaper'       => $viewer ? StaffChatWallpaper::forConversation($viewer, $myWallpaper) : null,
            'wallpaper_is_set' => (bool) $myWallpaper,
            'can_call'        => $myPivot && !$myLeftAt && \App\Models\Chat\StaffCall::isEnabled() && \App\Models\Chat\StaffCall::canBeHeldIn($this->resource),
            'can_get_help'    => $myPivot && !$myLeftAt && !$this->resource->isBotDirect() && \App\Models\Chat\StaffCall::canBeHeldIn($this->resource) && $participants->count() === 2,
            'last_message_at' => $this->last_message_at,
            'last_message'    => \App\Models\Chat\StaffCall::entryText($this->last_message_body ?? null),
            'last_message_user_id' => $this->last_message_user_id ?? null,
            'unread_count'    => (int) ($this->unread_count ?? 0),
            'has_mention'     => (bool) ($this->has_mention ?? false),
            'slack_direct_url' => $this->slackDirectUrl($viewer, $request),
        ];
    }
}
