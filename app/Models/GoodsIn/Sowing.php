<?php

/*
 * Author: Oggie Sutrisna
 * Created: Thu, 19 Dec 2024 Malaysia Time
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Models\GoodsIn;

use App\Enums\GoodsIn\Sowing\SowingTypeEnum;
use App\Models\Dispatching\DeliveryNote;
use App\Models\Dispatching\DeliveryNoteItem;
use App\Models\Inventory\Location;
use App\Models\Inventory\OrgStock;
use App\Models\Inventory\OrgStockMovement;
use App\Models\SysAdmin\User;
use App\Models\Traits\InShop;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Sowing Model - Used for return/sowing operations
 * This model handles the return of items to inventory locations,
 * separated from the Picking model for better code clarity.
 *
 * @property int $id
 * @property int $group_id
 * @property int $organisation_id
 * @property int|null $shop_id
 * @property int|null $delivery_note_id
 * @property int|null $delivery_note_item_id
 * @property string $quantity
 * @property int|null $org_stock_movement_id
 * @property int|null $return_id
 * @property int|null $return_item_id
 * @property int|null $stock_delivery_id
 * @property int|null $stock_delivery_item_id
 * @property int $org_stock_id
 * @property int|null $sower_user_id
 * @property int|null $location_id
 * @property int|null $original_picking_id
 * @property array<array-key, mixed> $data
 * @property string|null $sowed_at
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property SowingTypeEnum $type
 * @property-read \App\Models\Dispatching\DeliveryNote|null $deliveryNote
 * @property-read \App\Models\Dispatching\DeliveryNoteItem|null $deliveryNoteItem
 * @property-read \App\Models\SysAdmin\Group $group
 * @property-read Location|null $location
 * @property-read OrgStock $orgStock
 * @property-read OrgStockMovement|null $orgStockMovement
 * @property-read \App\Models\SysAdmin\Organisation $organisation
 * @property-read \App\Models\Catalogue\Shop|null $shop
 * @property-read User|null $sower
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Sowing newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Sowing newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Sowing query()
 * @mixin \Eloquent
 */
class Sowing extends Model
{
    use InShop;

    protected $casts = [
        'data' => 'array',
        'type' => SowingTypeEnum::class,
    ];

    protected $guarded = [];

    protected $attributes = [
        'data' => '{}',
    ];

    public function deliveryNoteItem(): BelongsTo
    {
        return $this->belongsTo(DeliveryNoteItem::class);
    }

    public function deliveryNote(): BelongsTo
    {
        return $this->belongsTo(DeliveryNote::class);
    }

    public function sower(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sower_user_id');
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class, 'location_id');
    }

    public function orgStock(): BelongsTo
    {
        return $this->belongsTo(OrgStock::class, 'org_stock_id');
    }

    public function orgStockMovement(): BelongsTo
    {
        return $this->belongsTo(OrgStockMovement::class, 'org_stock_movement_id');
    }
}
