<?php

/*
 * Author: eka yudinata (https://github.com/ekayudinata)
 * Copyright (c) 2026, eka yudinata
 */

namespace App\Models\Comms;

use App\Enums\Comms\WhatsappBulkRun\WhatsappBulkRunStateEnum;
use App\Models\Traits\InShop;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * One scheduled run of a WhatsApp outbox that sends to many customers at once (out of stock
 * in order, back in stock). Its recipients are split into delivery channels of 50, the same
 * way a WhatsApp campaign is.
 *
 * @property int $id
 * @property int $group_id
 * @property int $organisation_id
 * @property int $shop_id
 * @property int $whatsapp_outbox_id
 * @property WhatsappBulkRunStateEnum $state
 * @property int $recipients_count
 * @property int $number_sent
 * @property int $number_failed
 * @property \Illuminate\Support\Carbon|null $recipients_prepared_at
 * @property \Illuminate\Support\Carbon|null $start_sending_at
 * @property \Illuminate\Support\Carbon|null $sent_at
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read WhatsappOutbox $whatsappOutbox
 * @property-read \Illuminate\Database\Eloquent\Collection<int, WhatsappDeliveryChannel> $deliveryChannels
 * @property-read \Illuminate\Database\Eloquent\Collection<int, WhatsappRecipient> $recipients
 */
class WhatsappBulkRun extends Model
{
    use InShop;

    protected $table = 'whatsapp_bulk_runs';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'state'                  => WhatsappBulkRunStateEnum::class,
            'recipients_prepared_at' => 'datetime',
            'start_sending_at'       => 'datetime',
            'sent_at'                => 'datetime',
        ];
    }

    public function whatsappOutbox(): BelongsTo
    {
        return $this->belongsTo(WhatsappOutbox::class);
    }

    public function deliveryChannels(): HasMany
    {
        return $this->hasMany(WhatsappDeliveryChannel::class);
    }

    public function recipients(): HasMany
    {
        return $this->hasMany(WhatsappRecipient::class);
    }
}
