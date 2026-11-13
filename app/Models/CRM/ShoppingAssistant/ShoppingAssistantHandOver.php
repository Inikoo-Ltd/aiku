<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 12 Nov 2026, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Models\CRM\ShoppingAssistant;

use App\Enums\CRM\ShoppingAssistant\ShoppingAssistantHandOverReasonEnum;
use App\Enums\CRM\ShoppingAssistant\ShoppingAssistantHandOverRouteEnum;
use App\Models\Chat\ChatSession;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One question the AI shopping assistant passed to a person: why, by which route, and the chat it opened.
 *
 * @property int $id
 * @property int $shopping_assistant_conversation_id
 * @property int|null $customer_message_id
 * @property int|null $offer_message_id
 * @property int|null $chat_session_id
 * @property ShoppingAssistantHandOverReasonEnum $reason
 * @property ShoppingAssistantHandOverRouteEnum $route
 * @property bool $in_hours
 * @property bool $agent_available
 * @property string|null $question
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property-read ShoppingAssistantConversation $conversation
 * @property-read ChatSession|null $chatSession
 * @mixin \Eloquent
 */
class ShoppingAssistantHandOver extends Model
{
    public const UPDATED_AT = null;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'reason'          => ShoppingAssistantHandOverReasonEnum::class,
            'route'           => ShoppingAssistantHandOverRouteEnum::class,
            'in_hours'        => 'boolean',
            'agent_available' => 'boolean',
        ];
    }

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(ShoppingAssistantConversation::class, 'shopping_assistant_conversation_id');
    }

    public function chatSession(): BelongsTo
    {
        return $this->belongsTo(ChatSession::class);
    }
}
