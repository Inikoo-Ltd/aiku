<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 17 Nov 2026 Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Models\Goods;

use App\Enums\Goods\ProductDevelopment\ProductDevelopmentFormulationTypeEnum;
use App\Models\HumanResources\Employee;
use App\Models\Production\Artefact;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * A named stage of formulating an in-house SKU (e.g. the essential oil blend, then the finished product). Each
 * stage has its own trial runs and is done when one of them is approved; the SKU is approved when every stage is.
 *
 * @property int $id
 * @property int $product_development_id
 * @property int $product_development_item_id
 * @property int $position
 * @property string $name
 * @property int|null $artisan_id the artisan the development proposes; the production manager assigns the job
 * @property ProductDevelopmentFormulationTypeEnum $type
 * @property string|null $brief what this stage has to achieve
 * @property int|null $artefact_id a blend's or intermediate's own artefact, made on its first trial run
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read ProductDevelopment|null $productDevelopment
 * @property-read ProductDevelopmentItem|null $item
 * @property-read Employee|null $artisan
 * @property-read Artefact|null $artefact
 * @property-read \Illuminate\Database\Eloquent\Collection<int, ProductDevelopmentSample> $samples
 *
 * @mixin \Eloquent
 */
class ProductDevelopmentFormulation extends Model
{
    protected $table = 'product_development_formulations';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'type' => ProductDevelopmentFormulationTypeEnum::class,
        ];
    }

    public function productDevelopment(): BelongsTo
    {
        return $this->belongsTo(ProductDevelopment::class);
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(ProductDevelopmentItem::class, 'product_development_item_id');
    }

    public function artisan(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'artisan_id');
    }

    public function artefact(): BelongsTo
    {
        return $this->belongsTo(Artefact::class);
    }

    public function samples(): HasMany
    {
        return $this->hasMany(ProductDevelopmentSample::class)->orderBy('number');
    }
}
