<?php

/*
 * Author: stewicca <stewicalf@gmail.com>
 * Copyright (c) 2026, Steven Wicca Alfredo
 */

namespace App\Models\CRM;

use App\Enums\CRM\TrafficSource\AdProposalStateEnum;
use App\Enums\CRM\TrafficSource\AdProposalTypeEnum;
use App\Models\Catalogue\Shop;
use App\Models\SysAdmin\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * App\Models\CRM\TrafficSourceAdProposal
 *
 * @property int $id
 * @property int $group_id
 * @property int $organisation_id
 * @property int $shop_id
 * @property int $traffic_source_id
 * @property int|null $traffic_source_campaign_id
 * @property AdProposalTypeEnum $type
 * @property AdProposalStateEnum $state
 * @property string $fingerprint
 * @property array $payload
 * @property array $evidence
 * @property string|null $rationale
 * @property string $amount
 * @property int|null $decided_by_user_id
 * @property \Illuminate\Support\Carbon|null $decided_at
 * @property string|null $failure_reason
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\CRM\TrafficSource $trafficSource
 * @property-read \App\Models\CRM\TrafficSourceCampaign|null $trafficSourceCampaign
 * @property-read \App\Models\Catalogue\Shop $shop
 * @property-read \App\Models\SysAdmin\User|null $decidedBy
 * @method static Builder<static>|TrafficSourceAdProposal newModelQuery()
 * @method static Builder<static>|TrafficSourceAdProposal newQuery()
 * @method static Builder<static>|TrafficSourceAdProposal query()
 * @mixin \Eloquent
 */
class TrafficSourceAdProposal extends Model
{
    use HasFactory;

    protected $table = 'traffic_source_ad_proposals';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'type'       => AdProposalTypeEnum::class,
            'state'      => AdProposalStateEnum::class,
            'payload'    => 'array',
            'evidence'   => 'array',
            'amount'     => 'decimal:2',
            'decided_at' => 'datetime',
        ];
    }

    public function trafficSource(): BelongsTo
    {
        return $this->belongsTo(TrafficSource::class);
    }

    public function trafficSourceCampaign(): BelongsTo
    {
        return $this->belongsTo(TrafficSourceCampaign::class);
    }

    public function shop(): BelongsTo
    {
        return $this->belongsTo(Shop::class);
    }

    public function decidedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'decided_by_user_id');
    }

    public function scopeOpen(Builder $query): Builder
    {
        return $query->where('state', AdProposalStateEnum::OPEN);
    }
}
