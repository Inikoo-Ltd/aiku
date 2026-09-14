<?php

namespace App\Models\Comms;

use App\Enums\Comms\WhatsappDeliveryChannel\WhatsappDeliveryChannelStateEnum;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $whatsapp_campaign_id
 * @property int $number_messages
 * @property WhatsappDeliveryChannelStateEnum $state
 * @property \Illuminate\Support\Carbon|null $start_sending_at
 * @property \Illuminate\Support\Carbon|null $sent_at
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read WhatsappCampaign|null $whatsappCampaign
 */
class WhatsappDeliveryChannel extends Model
{
    protected $table = 'whatsapp_delivery_channels';

    protected $guarded = [];

    protected $casts = [
        'state'            => WhatsappDeliveryChannelStateEnum::class,
        'start_sending_at' => 'datetime',
        'sent_at'          => 'datetime',
    ];

    public function whatsappCampaign(): BelongsTo
    {
        return $this->belongsTo(WhatsappCampaign::class);
    }
}
