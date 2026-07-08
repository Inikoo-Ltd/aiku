<?php

/*
 * Author: Andi Ferdiawan
 * Created: Wed, 08 Jul 2026 16:40:00 Central Indonesia Time, Bali, Indonesia
 * Copyright (c) 2026, Inikoo Ltd
 */

namespace App\Models\Dispatching;

use App\Enums\Catalogue\Leaflet\LeafletTypeEnum;
use App\Enums\Dispatching\DeliveryNoteLeaflet\DeliveryNoteLeafletStateEnum;
use App\Models\Catalogue\ModelHasLeaflet;
use App\Models\Helpers\Media;
use App\Models\SysAdmin\User;
use App\Models\Traits\InShop;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $group_id
 * @property int $organisation_id
 * @property int|null $shop_id
 * @property int $delivery_note_id
 * @property int|null $model_has_leaflet_id
 * @property LeafletTypeEnum $type
 * @property string $name
 * @property int|null $media_id
 * @property string|null $message
 * @property int $copies
 * @property DeliveryNoteLeafletStateEnum $state
 * @property \Illuminate\Support\Carbon|null $printed_at
 * @property int|null $printed_by_user_id
 * @property array<array-key, mixed> $data
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read DeliveryNote $deliveryNote
 * @property-read Media|null $media
 * @property-read ModelHasLeaflet|null $modelHasLeaflet
 * @property-read User|null $printedBy
 * @mixin \Eloquent
 */
class DeliveryNoteLeaflet extends Model
{
    use InShop;

    protected $guarded = [];

    protected $casts = [
        'type'       => LeafletTypeEnum::class,
        'state'      => DeliveryNoteLeafletStateEnum::class,
        'printed_at' => 'datetime',
        'data'       => 'array',
    ];

    protected $attributes = [
        'data' => '{}',
    ];

    public function deliveryNote(): BelongsTo
    {
        return $this->belongsTo(DeliveryNote::class);
    }

    public function modelHasLeaflet(): BelongsTo
    {
        return $this->belongsTo(ModelHasLeaflet::class);
    }

    public function media(): BelongsTo
    {
        return $this->belongsTo(Media::class);
    }

    public function printedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'printed_by_user_id');
    }
}
