<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 11 Nov 2026, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Models\Dispatching;

use App\Models\SysAdmin\Organisation;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * What a carrier charges us, from the rate PDFs they send each year (HELP-4585).
 *
 * @property int $id
 * @property int $group_id
 * @property int $organisation_id
 * @property string $carrier
 * @property string $service
 * @property Carbon $valid_from
 * @property Carbon|null $valid_to
 * @property int $volumetric_kg_per_m3
 * @property string $max_kg
 * @property string $surcharge_percent
 * @property array<int, array{name: string, match: array<int, array{country: string, postcodes?: array<int, string>}>, bands: array<string, float>, extra_kg: float|null, volumetric_kg_per_m3?: int, max_kg?: float}> $zones
 * @property string|null $notes
 */
class CarrierRateCard extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'valid_from' => 'date',
            'valid_to'   => 'date',
            'zones'      => 'array',
        ];
    }

    public function organisation(): BelongsTo
    {
        return $this->belongsTo(Organisation::class);
    }

    public function scopeValidOn(Builder $query, string $date): Builder
    {
        return $query->where('valid_from', '<=', $date)
            ->where(fn (Builder $query) => $query->whereNull('valid_to')->orWhere('valid_to', '>=', $date));
    }
}
