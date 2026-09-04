<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 03 Sep 2026 12:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Models\Goods;

use App\Models\SysAdmin\Organisation;
use App\Models\SysAdmin\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $group_id
 * @property int $organisation_id
 * @property int $trade_unit_id
 * @property string $national_extension
 * @property string $reason
 * @property int $approved_by_user_id
 * @property \Illuminate\Support\Carbon $approved_at
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read TradeUnit $tradeUnit
 * @property-read Organisation $organisation
 * @property-read User $approvedBy
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TradeUnitTariffCodeOverride newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TradeUnitTariffCodeOverride newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TradeUnitTariffCodeOverride query()
 * @mixin \Eloquent
 */
class TradeUnitTariffCodeOverride extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'approved_at' => 'datetime',
        ];
    }

    public function tradeUnit(): BelongsTo
    {
        return $this->belongsTo(TradeUnit::class);
    }

    public function organisation(): BelongsTo
    {
        return $this->belongsTo(Organisation::class);
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by_user_id');
    }
}
