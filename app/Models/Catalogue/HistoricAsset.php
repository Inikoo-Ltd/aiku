<?php

/*
 *  Author: Raul Perusquia <raul@inikoo.com>
 *  Created: Fri, 02 Sept 2022 21:48:46 Malaysia Time, Kuala Lumpur, Malaysia
 *  Copyright (c) 2022, Raul A Perusquia Flores
 */

namespace App\Models\Catalogue;

use App\Models\Traits\HasHistory;
use Eloquent;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use OwenIt\Auditing\Contracts\Auditable;

/**
 * App\Models\Catalogue\HistoricAsset
 *
 * @property int $id
 * @property int $group_id
 * @property int $organisation_id
 * @property bool $status
 * @property int $asset_id
 * @property string $model_type
 * @property int $model_id
 * @property numeric|null $price unit price
 * @property string|null $code
 * @property string|null $name
 * @property numeric|null $units units in outer
 * @property string|null $unit mirror of asset model
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property \Illuminate\Support\Carbon|null $deleted_at
 * @property string|null $source_id
 * @property array<array-key, mixed>|null $tax_category Tax override map frozen at creation, like price; null on rows predating this column, which fall back to the live master map
 * @property-read \App\Models\Catalogue\Asset|null $asset
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Helpers\Audit> $audits
 * @property-read Model|\Eloquent $model
 * @property-read \App\Models\Catalogue\HistoricAssetStats|null $stats
 * @method static Builder<static>|HistoricAsset newModelQuery()
 * @method static Builder<static>|HistoricAsset newQuery()
 * @method static Builder<static>|HistoricAsset onlyTrashed()
 * @method static Builder<static>|HistoricAsset query()
 * @method static Builder<static>|HistoricAsset withTrashed(bool $withTrashed = true)
 * @method static Builder<static>|HistoricAsset withoutTrashed()
 * @mixin Eloquent
 */
class HistoricAsset extends Model implements Auditable
{
    use SoftDeletes;
    use HasHistory;


    protected $casts = [
        'tax_category' => 'array',
        'status' => 'boolean',
    ];

    protected $guarded = [];

    public function generateTags(): array
    {
        return [
            'catalogue',
        ];
    }

    protected array $auditEvents = [
        'update',
    ];

    public function asset(): BelongsTo
    {
        return $this->belongsTo(Asset::class);
    }

    public function stats(): HasOne
    {
        return $this->hasOne(HistoricAssetStats::class);
    }

    public function model(): MorphTo
    {
        return $this->morphTo();
    }


}
