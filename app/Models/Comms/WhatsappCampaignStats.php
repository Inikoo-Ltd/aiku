<?php

/*
 * Author: eka yudinata (https://github.com/ekayudinata)
 * Copyright (c) 2026, eka yudinata
 */

namespace App\Models\Comms;

use Eloquent;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * App\Models\Comms\WhatsappCampaignStats
 *
 * @property int $id
 * @property int $whatsapp_campaign_id
 * @property int $number_recipients
 * @property int $number_sent
 * @property int $number_delivered
 * @property int $number_read
 * @property int $number_clicked
 * @property int $number_failed
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\Comms\WhatsappCampaign|null $whatsappCampaign
 * @method static Builder<static>|WhatsappCampaignStats newModelQuery()
 * @method static Builder<static>|WhatsappCampaignStats newQuery()
 * @method static Builder<static>|WhatsappCampaignStats query()
 * @mixin Eloquent
 */
class WhatsappCampaignStats extends Model
{
    protected $table = 'whatsapp_campaign_stats';

    protected $guarded = [];

    public function whatsappCampaign(): BelongsTo
    {
        return $this->belongsTo(WhatsappCampaign::class);
    }
}
