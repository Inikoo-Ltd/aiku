<?php

namespace App\Models\Catalogue;

use Eloquent;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * App\Models\Catalogue\FamilyHasProductOrdered
 *
 * @property int $id
 * @property int $family_id
 * @property array<array-key, mixed> $product
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property \Illuminate\Support\Carbon|null $deleted_at
 * @property-read ProductCategory $family
 *
 * @method static Builder<static>|FamilyHasProductOrdered newModelQuery()
 * @method static Builder<static>|FamilyHasProductOrdered newQuery()
 * @method static Builder<static>|FamilyHasProductOrdered onlyTrashed()
 * @method static Builder<static>|FamilyHasProductOrdered query()
 * @method static Builder<static>|FamilyHasProductOrdered whereFamilyId($value)
 * @method static Builder<static>|FamilyHasProductOrdered whereId($value)
 * @method static Builder<static>|FamilyHasProductOrdered whereProduct($value)
 * @method static Builder<static>|FamilyHasProductOrdered withTrashed(bool $withTrashed = true)
 * @method static Builder<static>|FamilyHasProductOrdered withoutTrashed()
 * @mixin Eloquent
 */
class FamilyHasProductOrdered extends Model
{
    use SoftDeletes;
    use HasFactory;

    protected $casts = [
        'product' => 'array',
    ];

    protected $attributes = [
        'product' => '{}',
    ];

    protected $guarded = [];

    public function family(): BelongsTo
    {
        return $this->belongsTo(ProductCategory::class, 'family_id');
    }
}
