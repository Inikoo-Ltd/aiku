<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 14 Aug 2026 09:00:00 Central European Summer Time, Mijas, Spain
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Models\Production;

use App\Models\SysAdmin\Group;
use App\Models\SysAdmin\Organisation;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $group_id
 * @property int $organisation_id
 * @property int $production_id
 * @property string $code
 * @property string|null $name
 * @property numeric $hourly_rate
 * @property numeric|null $target_multiplier
 * @property bool $requires_approval
 * @property \Illuminate\Support\Carbon $effective_from
 * @property \Illuminate\Support\Carbon|null $effective_to
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read Group|null $group
 * @property-read Organisation $organisation
 * @property-read Production|null $production
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ManufacturePayBand newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ManufacturePayBand newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ManufacturePayBand query()
 * @mixin \Eloquent
 */
class ManufacturePayBand extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected $casts = [
        'effective_from' => 'datetime',
        'effective_to'   => 'datetime',
    ];

    public function group(): BelongsTo
    {
        return $this->belongsTo(Group::class);
    }

    public function organisation(): BelongsTo
    {
        return $this->belongsTo(Organisation::class);
    }

    public function production(): BelongsTo
    {
        return $this->belongsTo(Production::class);
    }
}
