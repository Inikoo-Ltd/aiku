<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 24 Sep 2026 03:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Models\Chat;

use App\Enums\CRM\Livechat\ChatAiDraftChangeEnum;
use App\Enums\CRM\Livechat\ChatAiDraftStatusEnum;
use App\Enums\CRM\Livechat\ChatAiReactionEnum;
use App\Enums\CRM\Livechat\ChatAiRiskEnum;
use App\Enums\CRM\Livechat\ChatIntentEnum;
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
 * @property \Illuminate\Support\Carbon|null $flagged_wrong_at
 * @property int|null $flagged_by_user_id
 * @property string|null $flagged_reason
 * @property int|null $rating
 * @property string|null $rating_reason
 * @property int|null $rated_by_user_id
 * @property \Illuminate\Support\Carbon|null $rated_at
 * @property ChatIntentEnum|null $intent
 * @property ChatAiRiskEnum|null $risk
 * @property bool|null $answers_all
 * @property bool|null $would_send
 * @property ChatAiDraftChangeEnum|null $change_class
 * @property string|null $words_kept
 * @property int|null $open_to_reply_seconds
 * @property int|null $lag_seconds
 * @property ChatAiReactionEnum|null $customer_reaction
 * @property array|null $jev_check
 * @property array|null $claim_check
 * @property \Illuminate\Support\Carbon|null $copied_at
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
        'flagged_wrong_at' => 'datetime',
        'rated_at'   => 'datetime',
        'intent'       => ChatIntentEnum::class,
        'risk'         => ChatAiRiskEnum::class,
        'answers_all'  => 'boolean',
        'would_send'   => 'boolean',
        'change_class' => ChatAiDraftChangeEnum::class,
        'words_kept'   => 'decimal:3',
        'customer_reaction' => ChatAiReactionEnum::class,
        'jev_check'    => 'array',
        'claim_check'  => 'array',
        'copied_at'    => 'datetime',
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
