<?php

namespace App\Http\Resources\CRM\Livechat;

use Illuminate\Http\Resources\Json\JsonResource;

class ChatSessionListResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array|\Illuminate\Contracts\Support\Arrayable|\JsonSerializable
     */
     public function toArray($request)
    {
        $lastMessage = null;
        if ($this->relationLoaded('messages') && $this->messages->isNotEmpty()) {
            $lastMessage = $this->messages->first();
        }

        $activeAssignment = null;
        if ($this->relationLoaded('assignments')) {
            $activeAssignment = $this->assignments->where('status', 'active')->first();
        }

        $userData = [
            'name' => 'Guest ' . substr($this->guest_identifier, -6),
            'email' => null,
            'avatar' => null,
        ];

        if ($this->relationLoaded('webUser') && $this->webUser) {
            $userData = [
                'name' => $this->webUser->name,
                'email' => $this->webUser->email,
                'avatar' => $this->webUser->avatar_url ?? null,
            ];
        }

        return [
            'ulid' => $this->ulid,
            'status' => $this->status,
            'guest_identifier' => $this->guest_identifier,
            'created_at' => $this->created_at->format('Y-m-d H:i:s'),
            'created_at_timestamp' => $this->created_at->timestamp,

            'user' => $userData,

            'last_message' => $lastMessage ? [
                'message' => $this->truncateMessage($lastMessage->message),
                'sender_type' => $lastMessage->sender_type,
                'created_at' => $lastMessage->created_at->format('Y-m-d H:i:s'),
                'created_at_timestamp' => $lastMessage->created_at->timestamp,
                'is_read' => $lastMessage->is_read,
            ] : [
                'message' => 'No messages yet',
                'sender_type' => null,
                'created_at' => null,
                'created_at_timestamp' => null,
                'is_read' => true,
            ],

            'assigned_agent' => $activeAssignment ? [
                'id' => $activeAssignment->agent->id,
                'name' => $activeAssignment->agent->user->name,
            ] : null,

            'unread_count' => $this->relationLoaded('messages')
                ? $this->messages->where('is_read', false)->count()
                : 0,

            // Metadata
            'message_count' => $this->relationLoaded('messages')
                ? $this->messages->count()
                : 0,
            'duration' => $this->created_at->diffForHumans(),
        ];
    }

    protected function truncateMessage($message, $length = 50)
    {
        if (strlen($message) <= $length) {
            return $message;
        }

        return substr($message, 0, $length) . '...';
    }

}
