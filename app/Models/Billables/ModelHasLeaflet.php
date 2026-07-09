<?php

/*
 * Author: Andi Ferdiawan
 * Created: Wed, 08 Jul 2026 16:40:00 Central Indonesia Time, Bali, Indonesia
 * Copyright (c) 2026, Inikoo Ltd
 */

namespace App\Models\Billables;

use App\Enums\Catalogue\Leaflet\LeafletStateEnum;
use App\Enums\Catalogue\Leaflet\LeafletTypeEnum;
use App\Models\Helpers\Media;
use App\Models\Traits\InShop;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property int $id
 * @property int $group_id
 * @property int $organisation_id
 * @property int|null $shop_id
 * @property int|null $packaging_id
 * @property string $model_type
 * @property int $model_id
 * @property int $leaflet_id
 * @property LeafletTypeEnum $type
 * @property string $name
 * @property int|null $media_id
 * @property LeafletStateEnum $state
 * @property int|null $number_pages
 * @property string|null $print_size
 * @property array<array-key, mixed> $data
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property \Illuminate\Support\Carbon|null $deleted_at
 * @property-read Leaflet $leaflet
 * @property-read Media|null $media
 * @property-read Model $model
 * @property-read Packaging|null $packaging
 * @mixin \Eloquent
 */
class ModelHasLeaflet extends Model
{
    use SoftDeletes;
    use InShop;

    protected $guarded = [];

    protected $casts = [
        'type'  => LeafletTypeEnum::class,
        'state' => LeafletStateEnum::class,
        'data'  => 'array',
    ];

    protected $attributes = [
        'data' => '{}',
    ];

    public function model(): MorphTo
    {
        return $this->morphTo();
    }

    public function leaflet(): BelongsTo
    {
        return $this->belongsTo(Leaflet::class);
    }

    public function packaging(): BelongsTo
    {
        return $this->belongsTo(Packaging::class);
    }

    public function media(): BelongsTo
    {
        return $this->belongsTo(Media::class);
    }
}
