<?php

namespace App\Models\Dispatching;

use App\Enums\Dispatching\DeliveryNoteItem\Return\ReturnDeliveryNoteItemStateEnum;
use App\Models\Inventory\OrgStock;
use App\Models\Traits\InShop;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

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
}
