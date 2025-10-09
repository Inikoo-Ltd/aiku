<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 03 May 2024 14:39:32 British Summer Time, Sheffield, UK
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Models\Procurement;

use App\Models\SysAdmin\Organisation;
use App\Models\Traits\HasUniversalSearch;
use App\Models\Traits\InOrganisation;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/**
 * @property int $id
 * @property int $group_id
 * @property int $organisation_id
 * @property int $partner_id
 * @property bool $status
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property array<array-key, mixed> $sources
 * @property-read \App\Models\SysAdmin\Group $group
 * @property-read Organisation $organisation
 * @property-read Organisation $partner
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Procurement\PurchaseOrder> $purchaseOrders
 * @property-read \App\Models\Procurement\OrgPartnerStats|null $stats
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Procurement\StockDelivery> $stockDeliveries
 * @property-read \App\Models\Helpers\UniversalSearch|null $universalSearch
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OrgPartner newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OrgPartner newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OrgPartner query()
 * @mixin \Eloquent
 */
class OrgPartner extends Model
{
    use InOrganisation;
    use HasUniversalSearch;

    protected $casts = [
        'sources'           => 'array',
    ];

    protected $attributes = [
        'sources' => '{}',
    ];


    protected $guarded = [];



    public function stats(): HasOne
    {
        return $this->hasOne(OrgPartnerStats::class);
    }

    public function purchaseOrders(): MorphMany
    {
        return $this->morphMany(PurchaseOrder::class, 'parent');
    }

    public function stockDeliveries(): MorphMany
    {
        return $this->morphMany(StockDelivery::class, 'parent');
    }

    public function partner(): BelongsTo
    {
        return $this->belongsTo(Organisation::class, 'partner_id');
    }


}
