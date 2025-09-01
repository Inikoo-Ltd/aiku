<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 11 Jul 2024 15:10:47 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Models\Helpers;

use App\Enums\Helpers\TaxCategories\TaxCategoryTypeEnum;
use Illuminate\Database\Eloquent\Model;
use Spatie\Sluggable\HasSlug;
use Spatie\Sluggable\SlugOptions;

/**
 * @property int $id
 * @property TaxCategoryTypeEnum $type
 * @property string $type_name
 * @property string $slug
 * @property string $label
 * @property string $name
 * @property bool $status
 * @property numeric $rate
 * @property int|null $country_id
 * @property array<array-key, mixed> $data
 * @property string|null $source_id
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TaxCategory newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TaxCategory newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TaxCategory query()
 * @mixin \Eloquent
 */
class TaxCategory extends Model
{
    use HasSlug;

    protected $casts = [
        'data'   => 'array',
        'status' => 'boolean',
        'rate'   => 'decimal:4',
        'type'   => TaxCategoryTypeEnum::class
    ];

    protected $attributes = [
        'data' => '{}',
    ];

    public function getSlugOptions(): SlugOptions
    {
        return SlugOptions::create()
            ->generateSlugsFrom(function () {
                return str_replace('+', '-', $this->label);
            })
            ->doNotGenerateSlugsOnUpdate()
            ->saveSlugsTo('slug');
    }


}
