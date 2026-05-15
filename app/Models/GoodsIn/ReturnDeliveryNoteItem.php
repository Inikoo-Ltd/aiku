<?php

/*
 * author Louis Perez
 * created on 05-05-2026-13h-38m
 * github: https://github.com/louis-perez
 * copyright 2026
*/

namespace App\Models\GoodsIn;

use App\Enums\GoodsIn\ReturnDeliveryNoteItem\ReturnDeliveryNoteItemStateEnum;
use App\Models\Dispatching\DeliveryNoteItem;
use App\Models\Inventory\OrgStock;
use App\Models\Traits\InShop;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int $group_id
 * @property int $organisation_id
 * @property int $shop_id
 * @property int $return_delivery_note_id
 * @property int $delivery_note_items_id
 * @property int|null $stock_family_id
 * @property int|null $stock_id
 * @property int|null $org_stock_family_id
 * @property int|null $org_stock_id
 * @property ReturnDeliveryNoteItemStateEnum $state
 * @property numeric $total_item_not_returned
 * @property numeric $total_item_damaged
 * @property numeric $total_item_returned
 * @property \Illuminate\Support\Carbon|null $handled_at
 * @property \Illuminate\Support\Carbon|null $processed_at
 * @property \Illuminate\Support\Carbon|null $cancelled_at
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property string|null $deleted_at
 * @property numeric $total_expected_qty
 * @property bool $is_handled
 * @property-read DeliveryNoteItem $deliveryNoteItems
 * @property-read \App\Models\SysAdmin\Group|null $group
 * @property-read OrgStock|null $orgStock
 * @property-read \App\Models\SysAdmin\Organisation $organisation
 * @property-read \App\Models\GoodsIn\ReturnDeliveryNote|null $returnDeliveryNote
 * @property-read \App\Models\Catalogue\Shop|null $shop
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\GoodsIn\Sowing> $sowings
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ReturnDeliveryNoteItem newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ReturnDeliveryNoteItem newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ReturnDeliveryNoteItem query()
 * @mixin \Eloquent
 */
class ReturnDeliveryNoteItem extends Model
{
    use InShop;

    protected $table = 'return_delivery_note_items';

    protected $casts = [
        'state'         => ReturnDeliveryNoteItemStateEnum::class,
        'handled_at'    => 'datetime',
        'processed_at'  => 'datetime',
        'cancelled_at'  => 'datetime',
    ];

    protected $attributes = [];

    protected $guarded = [];

    public function returnDeliveryNote(): BelongsTo
    {
        return $this->belongsTo(ReturnDeliveryNote::class);
    }

    public function deliveryNoteItems(): BelongsTo
    {
        return $this->belongsTo(DeliveryNoteItem::class);
    }

    public function orgStock(): BelongsTo
    {
        return $this->belongsTo(OrgStock::class);
    }

    public function sowings(): HasMany
    {
        return $this->hasMany(Sowing::class, 'return_item_id');
    }
}
