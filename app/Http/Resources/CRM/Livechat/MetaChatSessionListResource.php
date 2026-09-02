<?php

/*
 * Author: Andi Ferdiawan <dev@aw-advantage.com>
 * Copyright (c) 2026, Andi Ferdiawan
 */

namespace App\Http\Resources\CRM\Livechat;

use App\Enums\CRM\Livechat\ChatAssignmentStatusEnum;
use App\Enums\CRM\Livechat\ChatSessionStatusEnum;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;

class MetaChatSessionListResource extends JsonResource
{
    /**
     * @param  \Illuminate\Http\Request  $request
     * @return array|\Illuminate\Contracts\Support\Arrayable|\JsonSerializable
     */
    public function toArray($request): array
    {
        $lastMessage = null;
        if ($this->relationLoaded('messages') && $this->messages->isNotEmpty()) {
            $lastMessage = $this->messages->first();
        }

        $sessionIsClosed = $this->status === ChatSessionStatusEnum::CLOSED;
        $assignmentStatus = $sessionIsClosed
            ? ChatAssignmentStatusEnum::RESOLVED->value
            : ChatAssignmentStatusEnum::ACTIVE->value;

        $activeAssignment = null;
        if ($this->relationLoaded('assignments')) {
            $filtered = $this->assignments->where('status', $assignmentStatus);
            $activeAssignment = $sessionIsClosed
                ? $filtered->sortByDesc('updated_at')->first()
                : $filtered->first();
        }

        $hasActiveAssignment = $this->relationLoaded('assignments')
            && $this->assignments->contains('status', ChatAssignmentStatusEnum::ACTIVE);

        $status = $this->status;
        if ($status !== ChatSessionStatusEnum::CLOSED && !$hasActiveAssignment) {
            $status = ChatSessionStatusEnum::WAITING;
        }

        $guestProfile = null;
        if ($this->relationLoaded('events') && $this->events->isNotEmpty()) {
            $payload = $this->events->first()?->payload;
            if (!empty($payload)) {
                $guestProfile = [
                    'name' => $payload['name'] ?? null,
                    'email' => $payload['email'] ?? null,
                    'phone' => $payload['phone'] ?? null,
                ];
            }
        }

        if (!$guestProfile && (Arr::get($this->metadata ?? [], 'name') || $this->phone_number)) {
            $guestProfile = [
                'name'  => Arr::get($this->metadata ?? [], 'name'),
                'email' => null,
                'phone' => Arr::get($this->metadata ?? [], 'phone', $this->phone_number),
            ];
        }

        $customer = $this->customer_id ? $this->customer : null;

        $shop = $this->shop_id ? $this->shop : null;

        $aiSummary = null;
        if (isset($this->metadata['ai_summary'])) {
            $summaryData = $this->metadata['ai_summary'];
            $aiSummary = [
                'summary'     => Arr::get($summaryData, 'summary'),
                'key_points'  => Arr::get($summaryData, 'key_points', []),
                'sentiment'   => Arr::get($summaryData, 'sentiment', 'neutral'),
            ];
        }

        return [
            'id' => $this->id,
            'ulid' => $this->ulid,
            'status' => $status,
            'guest_identifier' => $this->guest_identifier ?? $this->phone_number,
            'phone_number' => $this->phone_number,
            'created_at' => $this->created_at,
            'priority' => $this->priority,
            'contact_name' => $customer?->contact_name
                ?? Arr::get($this->metadata ?? [], 'name')
                ?? $this->guest_identifier
                ?? $this->phone_number
                ?? 'Guest',
            'last_message' => $lastMessage ? [
                'message' => Str::limit($lastMessage->message_text, 50, '...'),
                'sender_type' => $lastMessage->sender_type,
                'created_at' => $lastMessage->created_at,
                'is_read' => $lastMessage->is_read,
            ] : [
                'message' => 'No messages yet',
                'sender_type' => null,
                'created_at' => null,
                'is_read' => true,
            ],
            'metadata' => $this->metadata ?? [],

            // A WhatsApp thread is keyed by phone number, so it links straight to the
            // customer rather than to a web user account.
            'customer' => $customer ? [
                'id' => $customer->id,
                'name' => $customer->contact_name ?? $customer->name,
                'slug' => $customer->slug,
                'email' => $customer->email,
                'phone' => $customer->phone,
                'organisation' => $customer->organisation?->name,
                'organisation_slug' => $customer->organisation?->slug,
                'shop' => $customer->shop?->name,
                'shop_slug' => $customer->shop?->slug,
                'image' => ['original' => '/retina-default-user.svg'],
            ] : null,

            'shop' => $shop ? [
                'id' => $shop->id,
                'name' => $shop->name,
                'slug' => $shop->slug,
                'domain' => $shop->website?->domain,
            ] : null,

            'organisation' => $shop ? [
                'id' => $shop->organisation->id,
                'name' => $shop->organisation->name,
                'slug' => $shop->organisation->slug,
            ] : null,

            'guest_profile' => $guestProfile ? [
                'name' => $guestProfile['name'],
                'email' => $guestProfile['email'],
                'phone' => $guestProfile['phone'],
                'image' => [
                    'original' => '/retina-default-user.svg'
                ]
            ] : null,

            'assigned_agent' => $activeAssignment ? [
                'id'      => $activeAssignment->chatAgent?->id,
                'user_id' => $activeAssignment->chatAgent?->user_id,
                'name'    => $activeAssignment->chatAgent?->user?->contact_name,
            ] : null,

            'is_spam'        => (bool) $this->is_spam,
            'is_highlighted' => (bool) $this->is_highlighted,

            'unread_count' => (int) ($this->unread_count ?? 0),

            'can_send_non_template_message' => $this->resource->can_send_non_template_message,

            'message_count' => $this->relationLoaded('messages')
                ? $this->messages->count()
                : 0,
            'duration' => $this->closed_at
                ? $this->created_at->diffForHumans($this->closed_at, true)
                : null,

            'ai_summary' => $aiSummary,
        ];
    }
}
