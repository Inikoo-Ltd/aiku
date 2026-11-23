<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 23 Nov 2026, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Models\Chat;

use App\Models\SysAdmin\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A question a customer service agent asked Aura about the chat they were answering, and her answer
 * (INI-519). Kept like Merlin conversations so we can review what staff ask.
 *
 * @property int $id
 * @property int $group_id
 * @property int $shop_id
 * @property int|null $chat_session_id
 * @property int|null $meta_chat_session_id
 * @property int $user_id
 * @property string $question
 * @property string|null $answer
 * @property array<int, array<string, mixed>> $tool_calls
 * @property array<int, string> $flags
 * @property string|null $model
 * @property int $model_calls
 * @property int $prompt_tokens
 * @property int $completion_tokens
 * @property string $cost
 * @property int|null $latency_ms
 * @property \Illuminate\Support\Carbon|null $answered_at
 * @property \Illuminate\Support\Carbon|null $redrafted_at
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read ChatSession|null $chatSession
 * @property-read MetaChatSession|null $metaChatSession
 * @property-read User $user
 */
class ChatAuraQuestion extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'tool_calls'   => 'array',
            'flags'        => 'array',
            'answered_at'  => 'datetime',
            'redrafted_at' => 'datetime',
        ];
    }

    protected $attributes = [
        'tool_calls' => '[]',
        'flags'      => '[]',
    ];

    public function chatSession(): BelongsTo
    {
        return $this->belongsTo(ChatSession::class);
    }

    public function metaChatSession(): BelongsTo
    {
        return $this->belongsTo(MetaChatSession::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function chat(): ChatSession|MetaChatSession|null
    {
        return $this->chatSession ?? $this->metaChatSession;
    }
}
