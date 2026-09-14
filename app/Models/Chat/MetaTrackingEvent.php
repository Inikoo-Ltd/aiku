<?php

/*
 * Author: Eka Yudinata <dev@aw-advantage.com>
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Models\Chat;

use App\Enums\CRM\Livechat\MetaTrackingEventTypeEnum;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int|null $meta_chat_session_id
 * @property int|null $meta_chat_message_id
 * @property string|null $meta_message_id
 * @property MetaTrackingEventTypeEnum $type
 * @property array|null $data
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property string|null $source_id
 * @property-read MetaChatSession|null $metaChatSession
 * @property-read MetaChatMessage|null $metaChatMessage
 */
class MetaTrackingEvent extends Model
{
    public $timestamps = false;

    protected $table = 'meta_tracking_events';

    protected $guarded = [];

    protected $casts = [
        'data'       => 'array',
        'type'       => MetaTrackingEventTypeEnum::class,
        'created_at' => 'datetime',
    ];

    protected $attributes = [
        'data' => '{}',
    ];

    public function metaChatSession(): BelongsTo
    {
        return $this->belongsTo(MetaChatSession::class);
    }

    public function metaChatMessage(): BelongsTo
    {
        return $this->belongsTo(MetaChatMessage::class);
    }
}
