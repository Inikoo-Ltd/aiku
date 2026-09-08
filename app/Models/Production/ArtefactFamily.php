<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 08 Sep 2026 Malaga, Spain
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Models\Production;

use App\Models\Inventory\OrgStockFamily;
use App\Models\Traits\HasHistory;
use App\Models\Traits\InProduction;
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
 * @property int $production_id
 * @property int $artefact_department_id
 * @property int|null $org_stock_family_id
 * @property string $slug
 * @property string $code
 * @property string $name
 * @property string|null $description
 * @property int $number_artefacts
 * @property array $data
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property \Illuminate\Support\Carbon|null $deleted_at
 * @mixin \Eloquent
 */
class ArtefactFamily extends Model implements Auditable
{
    use SoftDeletes;
    use HasSlug;
    use InProduction;
    use HasHistory;

    protected $casts = [
        'data' => 'array',
    ];

    protected $attributes = [
        'data' => '{}',
    ];

    protected $guarded = [];

    protected array $auditInclude = [
        'code',
        'name',
        'description',
        'artefact_department_id',
    ];

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function getSlugOptions(): SlugOptions
    {
        return SlugOptions::create()
            ->generateSlugsFrom(function () {
                return $this->artefactDepartment->code.' '.$this->code;
            })
            ->slugsShouldBeNoLongerThan(128)
            ->doNotGenerateSlugsOnUpdate()
            ->saveSlugsTo('slug');
    }

    public function artefactDepartment(): BelongsTo
    {
        return $this->belongsTo(ArtefactDepartment::class);
    }

    public function orgStockFamily(): BelongsTo
    {
        return $this->belongsTo(OrgStockFamily::class);
    }

    public function artefacts(): HasMany
    {
        return $this->hasMany(Artefact::class);
    }
}
