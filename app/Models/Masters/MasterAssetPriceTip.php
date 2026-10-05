<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 01 Oct 2026 09:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Models\Masters;

use App\Enums\Masters\MasterAsset\MasterAssetPriceTipStatusEnum;
use App\Models\Traits\HasHistory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use OwenIt\Auditing\Contracts\Auditable;

/**
 * A markdown or markup Jev suggested for a master product (HELP-2331), kept so staff can apply or
 * dismiss it and so the sales after an applied change can be measured against the sales before.
 * One open tip per master product at most; the nightly run refreshes it or expires it.
 *
 * @property int $id
 * @property int $group_id
 * @property int $master_shop_id
 * @property int $master_asset_id
 * @property int $change percent, negative is a markdown
 * @property string $confidence probability Jev gave the chosen change
 * @property array $probabilities
 * @property string|null $temporary_drop_probability
 * @property string $reason
 * @property array $state what Jev was shown
 * @property MasterAssetPriceTipStatusEnum $status
 * @property string $price base currency price when the tip was made
 * @property string|null $dismissed_reason
 * @property int|null $dismissed_by_user_id
 * @property \Illuminate\Support\Carbon|null $dismissed_at
 * @property string|null $applied_price
 * @property int|null $applied_by_user_id
 * @property \Illuminate\Support\Carbon|null $applied_at
 * @property array|null $outcome
 * @property \Illuminate\Support\Carbon|null $measured_at
 * @property \Illuminate\Support\Carbon|null $expired_at
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read MasterAsset $masterAsset
 * @property-read MasterShop $masterShop
 */
class MasterAssetPriceTip extends Model implements Auditable
{
    use HasHistory;

    protected $guarded = [];

    protected $casts = [
        'status'        => MasterAssetPriceTipStatusEnum::class,
        'probabilities' => 'array',
        'state'         => 'array',
        'outcome'       => 'array',
        'dismissed_at'  => 'datetime',
        'applied_at'    => 'datetime',
        'measured_at'   => 'datetime',
        'expired_at'    => 'datetime',
    ];

    protected array $auditInclude = [
        'status',
        'dismissed_reason',
        'applied_price',
    ];

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

    public function masterShop(): BelongsTo
    {
        return $this->belongsTo(MasterShop::class);
    }
}
