<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 08 Oct 2026 19:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Models\Goods;

use App\Models\Traits\InGroup;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * A reusable set of packaging components, shared by every trade unit packed the same way.
 *
 * @property int $id
 * @property int $group_id
 * @property string $code
 * @property string|null $name
 * @property string $status
 * @property string|null $signature
 * @property array<array-key, mixed> $data
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read \Illuminate\Database\Eloquent\Collection<int, PackagingComponent> $components
 * @property-read \Illuminate\Database\Eloquent\Collection<int, TradeUnit> $tradeUnits
 * @mixin \Eloquent
 */
class PackagingFamily extends Model
{
    use InGroup;

    protected $casts = [
        'data' => 'array',
    ];

    protected $attributes = [
        'data' => '{}',
    ];

    protected $guarded = [];

    public function components(): BelongsToMany
    {
        return $this->belongsToMany(PackagingComponent::class, 'packaging_family_has_components')
            ->withPivot(['quantity', 'quantity_per_unit'])->withTimestamps();
    }

    public function tradeUnits(): HasMany
    {
        return $this->hasMany(TradeUnit::class);
    }
}
