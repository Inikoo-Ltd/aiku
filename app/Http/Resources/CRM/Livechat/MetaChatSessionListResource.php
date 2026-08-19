<?php

/*
 * Author: Andi Ferdiawan <dev@aw-advantage.com>
 * Copyright (c) 2026, Andi Ferdiawan
 */

namespace App\Http\Resources\CRM\Livechat;

use App\Enums\CRM\Livechat\ChatAssignmentStatusEnum;
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
        $statuses = $request->input('statuses', []);
        $statusParam = $request->input('status');
        $isClosed = is_array($statuses) ? in_array('closed', $statuses) : ($statusParam === 'closed');
        $assignmentStatus = $isClosed ? ChatAssignmentStatusEnum::RESOLVED->value : ChatAssignmentStatusEnum::ACTIVE->value;

        $lastMessage = null;
        if ($this->relationLoaded('messages') && $this->messages->isNotEmpty()) {
            $lastMessage = $this->messages->first();
        }

        $activeAssignment = null;
        if ($this->relationLoaded('assignments')) {
            $filtered = $this->assignments->where('status', $assignmentStatus);
            $activeAssignment = $isClosed
                ? $filtered->sortByDesc('updated_at')->first()
                : $filtered->first();
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

        $webUser = $this->web_user_id ? $this->webUser : null;

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
            'status' => $this->status,
            'guest_identifier' => $this->guest_identifier ?? $this->phone_number,
            'phone_number' => $this->phone_number,
            'created_at' => $this->created_at,
            'priority' => $this->priority,
            'contact_name' => Arr::get($this->metadata ?? [], 'name')
                ?? $webUser?->customer?->contact_name
                ?? $webUser?->username
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

            'web_user' => $webUser ? [
                'id' => $webUser->id,
                'name' => $webUser->contact_name,
                'slug' => $webUser->customer?->slug,
                'email' => $webUser->customer?->email,
                'phone' => $webUser->customer?->phone,
                'organisation' => $webUser->customer?->organisation?->name,
                'organisation_slug' => $webUser->customer?->organisation?->slug,
                'shop' => $webUser->customer?->shop?->name,
                'shop_slug' => $webUser->customer?->shop?->slug,
                'image' => !blank($webUser->image_id)
                    ? $webUser->imageSources(320, 320)
                    : [
                        'original' => '/retina-default-user.svg'
                    ],
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
