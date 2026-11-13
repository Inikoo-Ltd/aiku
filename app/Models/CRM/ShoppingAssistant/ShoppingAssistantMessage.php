<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 11 Nov 2026, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Models\CRM\ShoppingAssistant;

use App\Enums\CRM\ShoppingAssistant\ShoppingAssistantAnswerKindEnum;
use App\Enums\CRM\ShoppingAssistant\ShoppingAssistantHandOverReasonEnum;
use App\Enums\CRM\ShoppingAssistant\ShoppingAssistantMessageRoleEnum;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int $shopping_assistant_conversation_id
 * @property ShoppingAssistantMessageRoleEnum $role
 * @property string|null $body
 * @property array<int, array{name: string, arguments: mixed, product_ids: array<int>, error: ?string, ms: int}>|null $tool_calls
 * @property array<int, string>|null $follow_ups
 * @property array<int, string>|null $flags
 * @property string|null $model
 * @property int $model_calls
 * @property int $prompt_tokens
 * @property int $completion_tokens
 * @property string $cost
 * @property int|null $latency_ms
 * @property string|null $feedback
 * @property \Illuminate\Support\Carbon|null $feedback_at
 * @property ShoppingAssistantAnswerKindEnum|null $answer_kind
 * @property string|null $source_kind
 * @property array<int, array{id: int, kind: string, source_type: string, source_id: ?string, title: string, url: ?string}>|null $sources
 * @property ShoppingAssistantHandOverReasonEnum|null $hand_over_offer
 * @property string|null $staff_rating
 * @property int|null $staff_rated_by_user_id
 * @property \Illuminate\Support\Carbon|null $staff_rated_at
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read ShoppingAssistantConversation $conversation
 * @property-read \Illuminate\Database\Eloquent\Collection<int, ShoppingAssistantEvent> $events
 * @mixin \Eloquent
 */
class ShoppingAssistantMessage extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'role'       => ShoppingAssistantMessageRoleEnum::class,
            'tool_calls' => 'array',
            'follow_ups' => 'array',
            'flags'      => 'array',
            'cost'       => 'decimal:6',
            'answer_kind'     => ShoppingAssistantAnswerKindEnum::class,
            'sources'         => 'array',
            'hand_over_offer' => ShoppingAssistantHandOverReasonEnum::class,
            'staff_rated_at'  => 'datetime',
            'feedback_at'     => 'datetime',
        ];
    }

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(ShoppingAssistantConversation::class, 'shopping_assistant_conversation_id');
    }

    public function events(): HasMany
    {
        return $this->hasMany(ShoppingAssistantEvent::class);
    }
}
