<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 24 Sep 2026 03:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Models\Chat;

use App\Enums\CRM\Livechat\ChatAiDraftStatusEnum;
use App\Enums\CRM\Livechat\ChatTopicEnum;
use App\Models\Catalogue\Shop;
use App\Models\SysAdmin\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A reply the AI wrote from facts looked up in aiku, waiting for staff to send, change or
 * throw away. Kept after it is decided, with what became of it, so the share sent as written
 * can be counted before anything is ever sent without a person.
 *
 * @property int $id
 * @property int $group_id
 * @property int $organisation_id
 * @property int $shop_id
 * @property int|null $chat_session_id
 * @property int|null $meta_chat_session_id
 * @property int|null $trigger_message_id
 * @property ChatTopicEnum $topic
 * @property array $facts
 * @property string $text
 * @property ChatAiDraftStatusEnum $status
 * @property \Illuminate\Support\Carbon|null $taken_at
 * @property int|null $reply_message_id
 * @property int|null $decided_by_user_id
 * @property \Illuminate\Support\Carbon|null $decided_at
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read ChatSession|null $chatSession
 * @property-read MetaChatSession|null $metaChatSession
 * @property-read Shop $shop
 * @property-read User|null $decidedBy
 */
class ChatAiDraft extends Model
{
    protected $guarded = [];

    protected $casts = [
        'topic'      => ChatTopicEnum::class,
        'facts'      => 'array',
        'status'     => ChatAiDraftStatusEnum::class,
        'taken_at'   => 'datetime',
        'decided_at' => 'datetime',
    ];

    protected $attributes = [
        'facts' => '{}',
    ];

    public function chatSession(): BelongsTo
    {
        return $this->belongsTo(ChatSession::class);
    }

    public function metaChatSession(): BelongsTo
    {
        return $this->belongsTo(MetaChatSession::class);
    }

    public function shop(): BelongsTo
    {
        return $this->belongsTo(Shop::class);
    }

    public function decidedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'decided_by_user_id');
    }

    public function session(): ChatSession|MetaChatSession|null
    {
        return $this->chatSession ?? $this->metaChatSession;
    }
}
