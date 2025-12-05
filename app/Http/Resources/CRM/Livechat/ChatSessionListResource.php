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

        $userData = false;

        if ($this->relationLoaded('webUser') && $this->webUser) {
            $userData = [
                'name' => $this->webUser->customer->contact_name,
                'email' => $this->webUser->email,
            ];
        }

        return [
            'ulid' => $this->ulid,
            'status' => $this->status,
            'guest_identifier' => $this->guest_identifier,
            'created_at' => $this->created_at->format('Y-m-d H:i:s'),
            'created_at_timestamp' => $this->created_at->copy()->setTimezone('UTC')->timestamp,

            'customer' => $userData,

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

            'assigned_agent' => $activeAssignment ? [
                'id' => $activeAssignment->chatAgent->id,
                'name' => $activeAssignment->chatAgent->user->contact_name ,
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
