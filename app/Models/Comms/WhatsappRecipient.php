<?php

namespace App\Models\Comms;

use App\Models\Chat\MetaChatMessage;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * @property int $id
 * @property int $whatsapp_campaign_id
 * @property int|null $meta_chat_message_id
 * @property int|null $whatsapp_delivery_channel_id
 * @property string $recipient_type Customer, MetaChatSession
 * @property int $recipient_id
 * @property string|null $recipient_name
 * @property string $phone normalised, digits only
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read WhatsappCampaign|null $whatsappCampaign
 * @property-read MetaChatMessage|null $metaChatMessage
 * @property-read WhatsappDeliveryChannel|null $deliveryChannel
 * @property-read Model|\Eloquent $recipient
 */
class WhatsappRecipient extends Model
{
    protected $table = 'whatsapp_recipients';

    protected $guarded = [];

    public function recipient(): MorphTo
    {
        return $this->morphTo();
    }

    public function whatsappCampaign(): BelongsTo
    {
        return $this->belongsTo(WhatsappCampaign::class);
    }

    public function metaChatMessage(): BelongsTo
    {
        return $this->belongsTo(MetaChatMessage::class);
    }

    public function deliveryChannel(): BelongsTo
    {
        return $this->belongsTo(WhatsappDeliveryChannel::class, 'whatsapp_delivery_channel_id');
    }
}
