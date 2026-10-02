<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 22 Aug 2026 14:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Http\Resources\Chat;

use App\Models\Analytics\UserRequest;
use App\Models\Chat\StaffConversation;
use App\Models\SysAdmin\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin StaffConversation
 */
class StaffConversationResource extends JsonResource
{
    protected function contextUrl(): ?string
    {
        $context = $this->context;
        if (!$context) {
            return null;
        }

        return match ($this->context_type) {
            'DeliveryNote' => route('grp.org.warehouses.show.dispatching.delivery_notes.show', [$context->organisation->slug, $context->warehouse->slug, $context->slug]),
            'Order'        => route('grp.org.shops.show.ordering.orders.show', [$context->organisation->slug, $context->shop->slug, $context->slug]),
            'StaffTask'    => route('grp.tasks.show', $context->reference),
            'ChatSession'  => route('grp.org.chat.conversations.detail', ['organisation' => $context->shop?->organisation?->slug, 'chatSession' => $context->id]),
            'PickingSession' => route('grp.org.warehouses.show.dispatching.picking_sessions.show', [$context->organisation->slug, $context->warehouse->slug, $context->slug]),
            default        => null,
        };
    }

    /**
     * @return array{reference: string, requester_id: int, assignee_id: int|null, collaborator_ids: int[], subtasks: array<int, array{title: string, status: string}>}|null
     */
    protected function task(): ?array
    {
        if ($this->context_type !== 'StaffTask' || !$this->context) {
            return null;
        }

        $task = $this->context;

        return [
            'reference'        => $task->reference,
            'requester_id'     => $task->requester_id,
            'assignee_id'      => $task->assignee_id,
            'collaborator_ids' => $task->collaborators->pluck('id')->all(),
            'subtasks'         => $task->data['subtasks'] ?? [],
        ];
    }

    public function toArray($request): array
    {
        $participants = $this->participants->map(fn (User $user) => [
            'id'     => $user->id,
            'name'   => $user->chatName(),
            'handle' => $user->nickname ?: $user->username,
            'avatar' => $user->image_id ? $user->imageSources(0, 48) : null,
            'last_seen_at' => Cache::remember('staff-last-seen:'.$user->id, 120, fn () => UserRequest::where('user_id', $user->id)->max('date')),
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
            'last_message_at' => $this->last_message_at,
            'last_message'    => $this->last_message_body ?? null,
            'unread_count'    => (int) ($this->unread_count ?? 0),
            'has_mention'     => (bool) ($this->has_mention ?? false),
        ];
    }
}
