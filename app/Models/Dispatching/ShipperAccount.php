<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 31 Dec 2024 19:12:28 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Models\Dispatching;

use App\Models\Traits\HasHistory;
use App\Models\Traits\InOrganisation;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use OwenIt\Auditing\Contracts\Auditable;

/**
 * @property int $id
 * @property int $group_id
 * @property int $organisation_id
 * @property int|null $shop_id
 * @property int|null $shipper_id
 * @property string $slug
 * @property string $code
 * @property bool $status
 * @property array<array-key, mixed> $data
 * @property array<array-key, mixed> $settings
 * @property array<array-key, mixed> $credentials
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Helpers\Audit> $audits
 * @property-read \App\Models\SysAdmin\Group $group
 * @property-read \App\Models\SysAdmin\Organisation $organisation
 * @property-read \App\Models\Dispatching\Shipper|null $shipper
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ShipperAccount newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ShipperAccount newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ShipperAccount query()
 * @mixin \Eloquent
 */
class ShipperAccount extends Model implements Auditable
{
    use HasHistory;
    use InOrganisation;

    protected $casts = [
        'data'            => 'array',
        'credentials'     => 'array',
        'settings'        => 'array',
        'status'          => 'boolean',
        'last_used_at'    => 'datetime',
        'fetched_at'      => 'datetime',
        'last_fetched_at' => 'datetime',
    ];

    protected $attributes = [
        'data'        => '{}',
        'credentials' => '{}',
        'settings'    => '{}',
    ];
    protected $guarded = [];

    public function generateTags(): array
    {
        return [
            'dispatching',
        ];
    }

    public function shipper(): BelongsTo
    {
        return $this->belongsTo(Shipper::class);
    }

}
