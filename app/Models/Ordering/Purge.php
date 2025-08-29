<?php

/*
 * author Arya Permana - Kirin
 * created on 01-11-2024-10h-47m
 * github: https://github.com/KirinZero0
 * copyright 2024
*/

namespace App\Models\Ordering;

use App\Enums\Ordering\Purge\PurgeStateEnum;
use App\Enums\Ordering\Purge\PurgeTypeEnum;
use App\Models\Traits\HasHistory;
use App\Models\Traits\InShop;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use OwenIt\Auditing\Contracts\Auditable;

/**
 * @property int $id
 * @property int $group_id
 * @property int $organisation_id
 * @property int $shop_id
 * @property int|null $user_id
 * @property PurgeStateEnum $state
 * @property PurgeTypeEnum $type
 * @property int|null $inactive_days
 * @property \Illuminate\Support\Carbon|null $scheduled_at
 * @property \Illuminate\Support\Carbon|null $start_at
 * @property \Illuminate\Support\Carbon|null $end_at
 * @property \Illuminate\Support\Carbon|null $cancelled_at
 * @property string|null $fetched_at
 * @property string|null $last_fetched_at
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property string|null $source_id
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Helpers\Audit> $audits
 * @property-read \App\Models\SysAdmin\Group $group
 * @property-read \App\Models\SysAdmin\Organisation $organisation
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Ordering\PurgedOrder> $purgedOrders
 * @property-read \App\Models\Catalogue\Shop $shop
 * @property-read \App\Models\Ordering\PurgeStats|null $stats
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Purge newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Purge newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Purge query()
 * @mixin \Eloquent
 */
class Purge extends Model implements Auditable
{
    use InShop;
    use HasHistory;

    protected $guarded = [];

    protected $casts = [
        'type'                 => PurgeTypeEnum::class,
        'state'                => PurgeStateEnum::class,
        'scheduled_at'         => 'datetime',
        'start_at'             => 'datetime',
        'end_at'               => 'datetime',
        'cancelled_at'         => 'datetime',
        'estimated_amount'     => 'decimal:2',
        'estimated_org_amount' => 'decimal:2',
        'estimated_grp_amount' => 'decimal:2',
        'purged_amount'        => 'decimal:2',
        'purged_org_amount'    => 'decimal:2',
        'purged_grp_amount'    => 'decimal:2',
    ];

    public function purgedOrders(): HasMany
    {
        return $this->hasMany(PurgedOrder::class);
    }

    public function stats(): HasOne
    {
        return $this->hasOne(PurgeStats::class);
    }

    public function generateTags(): array
    {
        return [
            'ordering'
        ];
    }

    protected array $auditInclude = [
        'scheduled_at',
    ];

}
