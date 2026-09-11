<?php

/*
 * Author: Andi Ferdiawan
 * Created: Wed, 08 Jul 2026 16:40:00 Central Indonesia Time, Bali, Indonesia
 * Copyright (c) 2026, Inikoo Ltd
 */

namespace App\Models\Billables;

use App\Enums\Catalogue\Leaflet\LeafletStateEnum;
use App\Enums\Catalogue\Leaflet\LeafletTypeEnum;
use App\Models\Helpers\Currency;
use App\Models\Traits\HasHistory;
use App\Models\Traits\InShop;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use OwenIt\Auditing\Contracts\Auditable;

/**
 * @property int $id
 * @property int $group_id
 * @property int $organisation_id
 * @property int|null $shop_id
 * @property int|null $packaging_id
 * @property array<array-key, string>|null $family_codes
 * @property string $name
 * @property LeafletTypeEnum $type
 * @property numeric $price
 * @property int $currency_id
 * @property LeafletStateEnum $state
 * @property array<array-key, mixed> $data
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property \Illuminate\Support\Carbon|null $deleted_at
 * @property-read Currency $currency
 * @property-read Packaging|null $packaging
 * @mixin \Eloquent
 */
class Leaflet extends Model implements Auditable
{
    use SoftDeletes;
    use HasHistory;
    use HasFactory;
    use InShop;

    protected $guarded = [];

    protected $casts = [
        'type'         => LeafletTypeEnum::class,
        'state'        => LeafletStateEnum::class,
        'price'        => 'decimal:2',
        'family_codes' => 'array',
        'data'         => 'array',
    ];

    protected $attributes = [
        'data' => '{}',
    ];

    protected array $auditInclude = [
        'name',
        'type',
        'price',
        'state',
        'packaging_id',
    ];

    public function generateTags(): array
    {
        return [
            'catalogue',
        ];
    }

    public function currency(): BelongsTo
    {
        return $this->belongsTo(Currency::class);
    }

    public function packaging(): BelongsTo
    {
        return $this->belongsTo(Packaging::class);
    }
}
