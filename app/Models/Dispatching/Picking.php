<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 23 Feb 2023 22:27:25 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Models\Dispatching;

use App\Enums\Dispatching\Picking\PickingNotPickedReasonEnum;
use App\Enums\Dispatching\Picking\PickingStateEnum;
use App\Enums\Dispatching\Picking\PickingEngineEnum;
use App\Models\SysAdmin\User;
use App\Models\Traits\InShop;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 *
 *
 * @property int $id
 * @property int $group_id
 * @property int $organisation_id
 * @property int $shop_id
 * @property int $delivery_note_id
 * @property int $delivery_note_item_id
 * @property PickingStateEnum $state
 * @property string $status
 * @property PickingNotPickedReasonEnum $not_picked_reason
 * @property string|null $not_picked_note
 * @property string $quantity_required
 * @property string|null $quantity_picked
 * @property int|null $org_stock_movement_id
 * @property int $org_stock_id
 * @property int|null $picker_id
 * @property PickingEngineEnum $engine
 * @property int|null $location_id
 * @property array<array-key, mixed> $data
 * @property string|null $queued_at
 * @property string|null $picking_at
 * @property string|null $picking_blocked_at
 * @property string|null $done_at
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\Dispatching\DeliveryNote $deliveryNote
 * @property-read \App\Models\Dispatching\DeliveryNoteItem $deliveryNoteItem
 * @property-read \App\Models\SysAdmin\Group $group
 * @property-read \App\Models\SysAdmin\Organisation $organisation
 * @property-read User|null $picker
 * @property-read \App\Models\Catalogue\Shop $shop
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Picking newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Picking newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Picking query()
 * @mixin \Eloquent
 */
class Picking extends Model
{
    use InShop;

    protected $casts = [
        'data'              => 'array',
        'state'             => PickingStateEnum::class,
        'not_picked_reason' => PickingNotPickedReasonEnum::class,
        'engine'            => PickingEngineEnum::class,
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

    public function picker(): BelongsTo
    {
        return $this->belongsTo(User::class, 'picker_id');
    }


}
