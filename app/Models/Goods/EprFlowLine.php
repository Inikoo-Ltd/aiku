<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 10 Oct 2026 13:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Models\Goods;

use App\Enums\Goods\Packaging\EprActivityEnum;
use App\Models\Traits\InOrganisation;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * One goods movement line classified for packaging EPR, rebuilt from stock deliveries and delivery notes by BuildEprFlowLines.
 * packaging_family_id is the trade unit's packaging when the line was built; reports read the trade unit's current one.
 *
 * @property int $id
 * @property int $group_id
 * @property int $organisation_id
 * @property Carbon $date
 * @property EprActivityEnum $activity
 * @property string $source_type
 * @property int $source_id
 * @property int|null $counterparty_country_id
 * @property int|null $org_stock_id
 * @property int|null $trade_unit_id
 * @property int|null $packaging_family_id
 * @property string $sko_quantity
 * @property string $quantity
 * @property string|null $shop_type
 * @property int|null $platform_id
 * @property Carbon|null $created_at
 * @property-read PackagingFamily|null $packagingFamily
 * @mixin \Eloquent
 */
class EprFlowLine extends Model
{
    use InOrganisation;

    public const UPDATED_AT = null;

    protected $casts = [
        'date'     => 'date',
        'activity' => EprActivityEnum::class,
    ];

    protected $guarded = [];

    public function packagingFamily(): BelongsTo
    {
        return $this->belongsTo(PackagingFamily::class);
    }
}
