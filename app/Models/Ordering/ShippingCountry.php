<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 11 Dec 2025 15:35:15 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Models\Ordering;

use App\Models\Catalogue\Shop;
use App\Models\Helpers\Country;
use App\Models\Traits\HasHistory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use OwenIt\Auditing\Contracts\Auditable;

/**
 * @property int $id
 * @property int $group_id
 * @property int $organisation_id
 * @property int $shop_id
 * @property int $country_id
 * @property array<array-key, mixed>|null $territories
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Helpers\Audit> $audits
 * @property-read Country $country
 * @property-read Shop $shop
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ShippingCountry newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ShippingCountry newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ShippingCountry query()
 * @mixin \Eloquent
 */
class ShippingCountry extends Model implements Auditable
{
    use HasFactory;
    use HasHistory;

    protected $table = 'shipping_countries';

    protected $guarded = [];

    protected $casts = [
        'territories' => 'array',
    ];

    public function shop(): BelongsTo
    {
        return $this->belongsTo(Shop::class);
    }

    public function country(): BelongsTo
    {
        return $this->belongsTo(Country::class);
    }

    public function generateTags(): array
    {
        return [
            'ordering'
        ];
    }

    protected array $auditInclude = [
        'territories',
    ];

}
