<?php

namespace App\Http\Resources\CRM\Livechat;

use App\Models\CRM\Livechat\ChatMessage;
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
            $activeAssignment = $this->assignments->first();
        }

        $guestProfile = null;
        if ($this->relationLoaded('chatEvents') && $this->chatEvents->isNotEmpty()) {
            $result = $this->chatEvents->first();
            if ($result && !empty($result->payload)) {
                $payload = $result->payload;
                $guestProfile = [
                    'name' => $payload['name'] ?? null,
                    'email' => $payload['email'] ?? null,
                    'phone' => $payload['phone'] ?? null,
                ];
            }
        }

        $webUser = $this->web_user_id ? $this->webUser : null;

        return [
            'ulid' => $this->ulid,
            'status' => $this->status,
            'guest_identifier' => $this->guest_identifier,
            'created_at' => $this->created_at->format('Y-m-d H:i:s'),
            'created_at_timestamp' => $this->created_at->copy()->setTimezone('UTC')->timestamp,
            'priority' => $this->priority,
            'contact_name' => $webUser?->customer?->contact_name,
            'last_message' => $lastMessage ? [
                'message' => $this->truncateMessage($lastMessage->message_text),
                'sender_type' => $lastMessage->sender_type,
                'created_at' => $this->created_at->format('Y-m-d H:i'),
                'created_at_timestamp' => $lastMessage->created_at->timestamp,
                'is_read' => $lastMessage->is_read,
            ] : [
                'message' => 'No messages yet',
                'sender_type' => null,
                'created_at' => null,
                'created_at_timestamp' => null,
                'is_read' => true,
            ],

            'web_user' => $webUser ? [
                'id' => $webUser->id,
                'name' => $webUser->contact_name,
                'slug' => $webUser->customer->slug,
                'email' => $webUser->customer->email,
                'phone' => $webUser->customer->phone,
                'slug' => $webUser->customer->slug,
                'organisation' => $webUser->customer->organisation->name,
                'organisation_slug' => $webUser->customer->organisation->slug,
                'shop' => $webUser->customer->shop->name,
                'shop_slug' => $webUser->customer->shop->slug,
            ] : null,

            'guest_profile' => $guestProfile ? [
                'name' => $guestProfile['name'],
                'email' => $guestProfile['email'],
                'phone' => $guestProfile['phone'],
            ] : null,

            'assigned_agent' => $activeAssignment ? [
                'id' => $activeAssignment->chatAgent->id,
                'name' => $activeAssignment->chatAgent->user->contact_name,
            ] : null,

            'unread_count' => ChatMessage::where('chat_session_id', $this->id)
                ->where('is_read', false)
                ->count(),

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
