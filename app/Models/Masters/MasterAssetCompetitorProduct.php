<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 01 Oct 2026 12:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Models\Masters;

use App\Enums\Masters\Competitor\MasterAssetCompetitorProductStatusEnum;
use App\Models\Traits\HasHistory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use OwenIt\Auditing\Contracts\Auditable;

/**
 * A competitor product the AI matched to one of ours, or one it looked at and turned down
 * (rejected, nobody reviewed it). Only confirmed matches reach the price tips.
 *
 * @property int $id
 * @property int $master_shop_id
 * @property int $master_asset_id
 * @property int $competitor_product_id
 * @property bool $is_same_item
 * @property string|null $confidence Jev's probability for the pick
 * @property MasterAssetCompetitorProductStatusEnum $status
 * @property int|null $reviewed_by_user_id
 * @property \Illuminate\Support\Carbon|null $reviewed_at
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read MasterAsset $masterAsset
 * @property-read CompetitorProduct $competitorProduct
 */
class MasterAssetCompetitorProduct extends Model implements Auditable
{
    use HasHistory;

    protected $guarded = [];

    protected array $auditInclude = [
        'status',
    ];

    protected function casts(): array
    {
        return [
            'status'       => MasterAssetCompetitorProductStatusEnum::class,
            'is_same_item' => 'boolean',
            'reviewed_at'  => 'datetime',
        ];
    }

    public function generateTags(): array
    {
        return [
            'catalogue',
        ];
    }

    public function masterAsset(): BelongsTo
    {
        return $this->belongsTo(MasterAsset::class);
    }

    public function competitorProduct(): BelongsTo
    {
        return $this->belongsTo(CompetitorProduct::class);
    }
}
