<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 25 Jul 2024 17:02:43 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Models\Fulfilment;

use App\Enums\Fulfilment\StoredItemAudit\StoredItemAuditStateEnum;
use App\Models\Inventory\Warehouse;
use App\Models\Traits\HasHistory;
use App\Models\Traits\HasRetinaSearch;
use App\Models\Traits\HasUniversalSearch;
use App\Models\Traits\InFulfilmentCustomer;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use OwenIt\Auditing\Contracts\Auditable;
use Spatie\Sluggable\HasSlug;
use Spatie\Sluggable\SlugOptions;

/**
 * @property int $id
 * @property string|null $ulid
 * @property int $group_id
 * @property int $organisation_id
 * @property int $fulfilment_customer_id
 * @property int $fulfilment_id
 * @property int|null $warehouse_id
 * @property string $slug
 * @property string $reference
 * @property StoredItemAuditStateEnum $state
 * @property \Illuminate\Support\Carbon|null $in_process_at
 * @property \Illuminate\Support\Carbon|null $completed_at
 * @property string|null $public_notes
 * @property string|null $internal_notes
 * @property array<array-key, mixed>|null $data
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property string|null $date
 * @property string $net_amount
 * @property string|null $grp_net_amount
 * @property string|null $org_net_amount
 * @property int|null $tax_category_id
 * @property string|null $tax_amount
 * @property string|null $total_amount
 * @property int $number_audited_pallets
 * @property int $number_audited_stored_items
 * @property int $number_audited_stored_items_with_additions Number of stored items with stock additions (found stock)
 * @property int $number_audited_stored_items_with_with_subtractions Number of stored items with stock subtractions (lost stock)
 * @property int $number_audited_stored_items_with_with_stock_checked Number of stored items with stock checked (stock was correct)
 * @property int $number_associated_stored_items Number of stored items associated to the pallet during the audit
 * @property int $number_created_stored_items Number of stored items created and associated to the pallet during the audit
 * @property string|null $scope_type Fulfilment|Pallet
 * @property int|null $scope_id
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Helpers\Audit> $audits
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Fulfilment\StoredItemAuditDelta> $deltas
 * @property-read \App\Models\Fulfilment\Fulfilment $fulfilment
 * @property-read \App\Models\Fulfilment\FulfilmentCustomer $fulfilmentCustomer
 * @property-read \App\Models\SysAdmin\Group $group
 * @property-read \App\Models\SysAdmin\Organisation $organisation
 * @property-read \App\Models\Helpers\RetinaSearch|null $retinaSearch
 * @property-read Model|\Eloquent|null $scope
 * @property-read \App\Models\Helpers\UniversalSearch|null $universalSearch
 * @property-read Warehouse|null $warehouse
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StoredItemAudit newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StoredItemAudit newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StoredItemAudit query()
 * @mixin \Eloquent
 */
class StoredItemAudit extends Model implements Auditable
{
    use HasSlug;
    use HasUniversalSearch;
    use HasRetinaSearch;
    use InFulfilmentCustomer;
    use HasHistory;

    protected $guarded = [];

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    protected $casts = [
        'state'         => StoredItemAuditStateEnum::class,
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

    public function scope()
    {
        return $this->morphTo();
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function deltas(): HasMany
    {
        return $this->hasMany(StoredItemAuditDelta::class);
    }


}
