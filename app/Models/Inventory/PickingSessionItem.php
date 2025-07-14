<?php

/*
 * author Arya Permana - Kirin
 * created on 07-07-2025-18h-09m
 * github: https://github.com/KirinZero0
 * copyright 2025
*/

namespace App\Models\Inventory;

use App\Models\Dispatching\DeliveryNoteItem;
use App\Models\Goods\Stock;
use App\Models\Goods\StockFamily;
use App\Models\SysAdmin\Group;
use App\Models\SysAdmin\Organisation;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 *
 *
 * @property int $id
 * @property int $group_id
 * @property int $organisation_id
 * @property int $warehouse_id
 * @property int $location_id
 * @property int|null $picking_session_id
 * @property int|null $stock_family_id
 * @property int|null $stock_id
 * @property int|null $org_stock_family_id
 * @property int|null $org_stock_id
 * @property string|null $notes
 * @property string $quantity_required
 * @property string|null $quantity_picked
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \Illuminate\Database\Eloquent\Collection<int, DeliveryNoteItem> $deliveryNotes
 * @property-read Group $group
 * @property-read \App\Models\Inventory\Location $location
 * @property-read \App\Models\Inventory\OrgStock|null $orgStock
 * @property-read \App\Models\Inventory\OrgStockFamily|null $orgStockFamily
 * @property-read Organisation $organisation
 * @property-read \App\Models\Inventory\PickingSession|null $pickingSession
 * @property-read Stock|null $stock
 * @property-read StockFamily|null $stockFamily
 * @property-read \App\Models\Inventory\Warehouse $warehouse
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PickingSessionItem newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PickingSessionItem newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PickingSessionItem query()
 * @mixin \Eloquent
 */
class PickingSessionItem extends Model
{
    use HasFactory;

    protected $guarded = [];

    public function group(): BelongsTo
    {
        return $this->belongsTo(Group::class);
    }

    public function organisation(): BelongsTo
    {
        return $this->belongsTo(Organisation::class);
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function deliveryNotes(): BelongsToMany
    {
        return $this->belongsToMany(
            DeliveryNoteItem::class,
            'picking_session_item_has_delivery_note_items',
            'picking_session_item_id',
            'delivery_note_item_id'
        );
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    public function pickingSession(): BelongsTo
    {
        return $this->belongsTo(PickingSession::class);
    }

    public function stockFamily(): BelongsTo
    {
        return $this->belongsTo(StockFamily::class);
    }

    public function stock(): BelongsTo
    {
        return $this->belongsTo(Stock::class);
    }

    public function orgStockFamily(): BelongsTo
    {
        return $this->belongsTo(OrgStockFamily::class);
    }

    public function orgStock(): BelongsTo
    {
        return $this->belongsTo(OrgStock::class);
    }
}
