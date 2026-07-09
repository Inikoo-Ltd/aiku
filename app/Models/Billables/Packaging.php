<?php

/*
 * Author: Andi Ferdiawan
 * Created: Wed, 08 Jul 2026 16:40:00 Central Indonesia Time, Bali, Indonesia
 * Copyright (c) 2026, Inikoo Ltd
 */

namespace App\Models\Billables;

use App\Enums\Catalogue\Packaging\PackagingStateEnum;
use App\Enums\Catalogue\Packaging\PackagingTypeEnum;
use App\Models\Dispatching\Box;
use App\Models\Helpers\Currency;
use App\Models\Helpers\Media;
use App\Models\Traits\HasHistory;
use App\Models\Traits\InShop;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use OwenIt\Auditing\Contracts\Auditable;
use Spatie\Sluggable\HasSlug;
use Spatie\Sluggable\SlugOptions;

/**
 * @property int $id
 * @property int $group_id
 * @property int $organisation_id
 * @property int|null $shop_id
 * @property string $slug
 * @property string $family_code
 * @property string $code
 * @property string $name
 * @property PackagingTypeEnum $type
 * @property numeric $price
 * @property int $currency_id
 * @property int|null $width
 * @property int|null $height
 * @property int|null $depth
 * @property int|null $box_id
 * @property int|null $image_id
 * @property PackagingStateEnum $state
 * @property int $position
 * @property array<array-key, mixed> $data
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property \Illuminate\Support\Carbon|null $deleted_at
 * @property-read Box|null $box
 * @property-read Currency $currency
 * @property-read Media|null $image
 * @property-read \Illuminate\Database\Eloquent\Collection<int, Leaflet> $leaflets
 * @mixin \Eloquent
 */
class Packaging extends Model implements Auditable
{
    use SoftDeletes;
    use HasSlug;
    use HasHistory;
    use HasFactory;
    use InShop;

    protected $guarded = [];

    protected $casts = [
        'type'  => PackagingTypeEnum::class,
        'state' => PackagingStateEnum::class,
        'price' => 'decimal:2',
        'data'  => 'array',
    ];

    protected $attributes = [
        'data' => '{}',
    ];

    protected array $auditInclude = [
        'family_code',
        'code',
        'name',
        'type',
        'price',
        'state',
    ];

    public function generateTags(): array
    {
        return [
            'catalogue',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function getSlugOptions(): SlugOptions
    {
        return SlugOptions::create()
            ->generateSlugsFrom('code')
            ->saveSlugsTo('slug')
            ->doNotGenerateSlugsOnUpdate()
            ->slugsShouldBeNoLongerThan(128);
    }

    public function currency(): BelongsTo
    {
        return $this->belongsTo(Currency::class);
    }

    public function box(): BelongsTo
    {
        return $this->belongsTo(Box::class);
    }

    public function image(): BelongsTo
    {
        return $this->belongsTo(Media::class, 'image_id');
    }

    public function leaflets(): HasMany
    {
        return $this->hasMany(Leaflet::class);
    }
}
