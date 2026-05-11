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

class ReturnDeliveryNoteItem extends Model
{
    use InShop;

    protected $table = 'return_delivery_note_items';

    protected $casts = [
        'return_state'  => ReturnDeliveryNoteItemStateEnum::class,
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
