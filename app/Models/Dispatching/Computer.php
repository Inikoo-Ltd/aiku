<?php

/*
 * Author: Ganes <gustiganes@gmail.com>
 * Created on: 04-07-2025, Bali, Indonesia
 * Github: https://github.com/Ganes556
 * Copyright: 2025
 *
*/

namespace App\Models\Dispatching;

use App\Models\Traits\InShop;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Sluggable\HasSlug;
use Spatie\Sluggable\SlugOptions;

/**
 * App\Models\Dispatching\Computer
 *
 * @property int $id
 * @property int|null $group_id
 * @property int|null $organisation_id
 * @property int|null $shop_id
 * @property int|null $shipment_id
 * @property string $slug
 * @property string $name
 * @property string|null $serial_number
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\Group|null $group
 * @property-read \App\Models\Organisation|null $organisation
 * @property-read \App\Models\Shop|null $shop
 * @property-read \App\Models\Shipment|null $shipment
 * @method static \Illuminate\Database\Eloquent\Builder|Computer newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|Computer newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|Computer query()
 * @mixin \Eloquent
 */
class Computer extends Model
{
    use HasFactory;
    use InShop;
    use HasSlug;

    protected $table = 'computers';

    protected $guarded = [];

    public function getSlugOptions(): SlugOptions
    {
        return SlugOptions::create()
            ->generateSlugsFrom('name')
            ->saveSlugsTo('slug')
            ->doNotGenerateSlugsOnUpdate();
    }
    public function shipment(): BelongsTo
    {
        return $this->belongsTo(Shipment::class, 'shipment_id');
    }

    public function printers(): HasMany
    {
        return $this->hasMany(Printer::class, 'computer_id');
    }
}
