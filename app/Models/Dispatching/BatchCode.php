<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 21 Apr 2026 14:10:58 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Models\Dispatching;

use App\Models\Inventory\OrgStock;
use App\Models\Traits\InOrganisation;
use Eloquent;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * App\Models\Dispatching\BatchCode
 *
 * @property int $id
 * @property int $group_id
 * @property int $organisation_id
 * @property int|null $org_stock_id
 * @property string $code
 * @property \Illuminate\Support\Carbon|null $expiry_date
 * @property int $number_delivery_notes
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\SysAdmin\Group|null $group
 * @property-read \App\Models\SysAdmin\Organisation $organisation
 * @property-read OrgStock|null $orgStock
 * @property-read Collection<int, DeliveryNoteItem> $deliveryNoteItems
 * @method static Builder<static>|BatchCode newModelQuery()
 * @method static Builder<static>|BatchCode newQuery()
 * @method static Builder<static>|BatchCode query()
 * @mixin Eloquent
 */
class BatchCode extends Model
{
    use InOrganisation;

    protected $casts = [
        'expiry_date' => 'date',
    ];

    protected $guarded = [];

    public function orgStock(): BelongsTo
    {
        return $this->belongsTo(OrgStock::class);
    }

    public function deliveryNoteItems(): HasMany
    {
        return $this->hasMany(DeliveryNoteItem::class);
    }
}
