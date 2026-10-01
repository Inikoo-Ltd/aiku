<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 30 Sep 2026 23:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Models\Chat;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One customer message as Jev's cascade read it: which area it fell in, what staff were shown
 * (a draft is counted from chat_ai_drafts by the same message, guides, facts, the programmers
 * card), what they used and what the agent actually wrote back, so the AI tab can say how
 * often the inbox had help to offer and where the suggestions and the real answers part.
 *
 * @property int $id
 * @property int $group_id
 * @property int $organisation_id
 * @property int $shop_id
 * @property int|null $chat_session_id
 * @property int|null $meta_chat_session_id
 * @property int|null $customer_message_id
 * @property string|null $customer_wrote
 * @property array|null $suggested
 * @property string|null $branch
 * @property string|null $topic
 * @property int $guides
 * @property bool $facts
 * @property bool $engineer
 * @property string|null $engineer_ticket
 * @property string|null $next_step
 * @property string|null $used
 * @property \Illuminate\Support\Carbon|null $used_at
 * @property int|null $reply_message_id
 * @property string|null $reply
 * @property \Illuminate\Support\Carbon|null $replied_at
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read ChatSession|null $chatSession
 * @property-read MetaChatSession|null $metaChatSession
 */
class ChatTurnReading extends Model
{
    protected $guarded = [];

    protected $casts = [
        'suggested'  => 'array',
        'facts'    => 'boolean',
        'engineer' => 'boolean',
        'used_at'    => 'datetime',
        'replied_at' => 'datetime',
    ];

    public function chatSession(): BelongsTo
    {
        return $this->belongsTo(ChatSession::class);
    }

    public function metaChatSession(): BelongsTo
    {
        return $this->belongsTo(MetaChatSession::class);
    }
}
