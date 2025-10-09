<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 26 Jul 2024 13:54:09 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Models\Fulfilment;

use App\Enums\Fulfilment\StoredItemAuditDelta\StoredItemAuditDeltaStateEnum;
use App\Enums\Fulfilment\StoredItemAuditDelta\StoredItemAuditDeltaTypeEnum;
use App\Models\SysAdmin\Group;
use App\Models\SysAdmin\Organisation;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $group_id
 * @property int $organisation_id
 * @property int|null $stored_item_audit_id
 * @property int $pallet_id
 * @property int $stored_item_id
 * @property \Illuminate\Support\Carbon|null $audited_at
 * @property int|null $user_id User who audited the stock
 * @property string|null $original_quantity
 * @property string $audited_quantity
 * @property StoredItemAuditDeltaStateEnum|null $state
 * @property StoredItemAuditDeltaTypeEnum|null $audit_type
 * @property string|null $notes
 * @property array<array-key, mixed> $data
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property int|null $location_id
 * @property bool $is_new_stored_item Stored item just created
 * @property bool $is_stored_item_new_in_pallet Existing Stored item was associated to the pallet
 * @property-read Group $group
 * @property-read Organisation $organisation
 * @property-read \App\Models\Fulfilment\Pallet $pallet
 * @property-read \App\Models\Fulfilment\StoredItem $storedItem
 * @property-read \App\Models\Fulfilment\StoredItemAudit|null $storedItemAudit
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StoredItemAuditDelta newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StoredItemAuditDelta newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StoredItemAuditDelta query()
 * @mixin \Eloquent
 */
class StoredItemAuditDelta extends Model
{
    protected $table = 'stored_item_audit_deltas';

    protected $guarded = [];

    protected $casts = [
        'audit_type' => StoredItemAuditDeltaTypeEnum::class,
        'state' => StoredItemAuditDeltaStateEnum::class,
        'audited_at' => 'datetime',
        'data'       => 'array'
    ];

    protected $attributes = [
        'data' => '{}'
    ];

    public function group(): BelongsTo
    {
        return $this->belongsTo(Group::class);
    }

    public function organisation(): BelongsTo
    {
        return $this->belongsTo(Organisation::class);
    }

    public function storedItemAudit(): BelongsTo
    {
        return $this->belongsTo(StoredItemAudit::class);
    }

    public function pallet(): BelongsTo
    {
        return $this->belongsTo(Pallet::class);
    }

    public function storedItem(): BelongsTo
    {
        return $this->belongsTo(StoredItem::class);
    }
}
