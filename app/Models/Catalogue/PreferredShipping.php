<?php

/*
 * Author: ekayudinata <dev@aw-advantage.com>
 * Created: Mon, 20 Jul 2026
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Models\Catalogue;

use App\Models\Dispatching\Shipper;
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
 * @property int|null $country_id
 * @property string|null $postcode
 * @property int $shipper_id
 * @property bool $important
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Helpers\Audit> $audits
 * @property-read Country|null $country
 * @property-read Shop|null $shop
 * @property-read Shipper|null $shipper
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PreferredShipping newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PreferredShipping newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PreferredShipping query()
 * @mixin \Eloquent
 */
class PreferredShipping extends Model implements Auditable
{
    use HasFactory;
    use HasHistory;

    protected $table = 'preferred_shippings';

    protected $guarded = [];

    public function shop(): BelongsTo
    {
        return $this->belongsTo(Shop::class);
    }

    public function country(): BelongsTo
    {
        return $this->belongsTo(Country::class);
    }

    public function shipper(): BelongsTo
    {
        return $this->belongsTo(Shipper::class);
    }

    public function generateTags(): array
    {
        return [
            'catalogue'
        ];
    }

    protected function casts(): array
    {
        return [
            'important' => 'boolean',
        ];
    }

    protected array $auditInclude = [
        'country_id',
        'postcode',
        'shipper_id',
        'important',
    ];

}
