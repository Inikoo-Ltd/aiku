<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 29 Aug 2024 11:54:59 Central Indonesia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Models\Inventory;

use App\Enums\Inventory\OrgStockAudit\OrgStockAuditStateEnum;
use App\Models\Traits\HasHistory;
use App\Models\Traits\InWarehouse;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use OwenIt\Auditing\Contracts\Auditable;
use Spatie\Sluggable\SlugOptions;

/**
 * @property int $id
 * @property int $group_id
 * @property int $organisation_id
 * @property int|null $warehouse_id
 * @property string $slug
 * @property string $reference
 * @property OrgStockAuditStateEnum $state
 * @property \Illuminate\Support\Carbon|null $in_process_at
 * @property \Illuminate\Support\Carbon|null $completed_at
 * @property string|null $public_notes
 * @property string|null $internal_notes
 * @property array<array-key, mixed>|null $data
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Helpers\Audit> $audits
 * @property-read \App\Models\SysAdmin\Group $group
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Inventory\OrgStockAuditDelta> $orgStockAuditDeltas
 * @property-read \App\Models\SysAdmin\Organisation $organisation
 * @property-read \App\Models\Inventory\Warehouse|null $warehouse
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OrgStockAudit newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OrgStockAudit newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OrgStockAudit query()
 * @mixin \Eloquent
 */
class OrgStockAudit extends Model implements Auditable
{
    use HasHistory;
    use InWarehouse;

    protected $guarded = [];

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    protected $casts = [
        'state'         => OrgStockAuditStateEnum::class,
        'in_process_at' => 'datetime',
        'completed_at'  => 'datetime',
        'data'          => 'array'
    ];

    public function getSlugOptions(): SlugOptions
    {
        return SlugOptions::create()
            ->generateSlugsFrom('reference')
            ->doNotGenerateSlugsOnUpdate()
            ->saveSlugsTo('slug')
            ->slugsShouldBeNoLongerThan(128);
    }

    public function orgStockAuditDeltas(): HasMany
    {
        return $this->hasMany(OrgStockAuditDelta::class);
    }

}
