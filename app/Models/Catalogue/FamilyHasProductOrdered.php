<?php

namespace App\Models\Catalogue;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * App\Models\Catalogue\FamilyHasProductOrdered
 *
 * @property int $id
 * @property int $family_id
 * @property array<array-key, mixed> $product
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read ProductCategory $family
 *
 * @method static Builder<static>|FamilyHasProductOrdered newModelQuery()
 * @method static Builder<static>|FamilyHasProductOrdered newQuery()
 * @method static Builder<static>|FamilyHasProductOrdered query()
 * @method static Builder<static>|FamilyHasProductOrdered whereFamilyId($value)
 * @method static Builder<static>|FamilyHasProductOrdered whereId($value)
 * @method static Builder<static>|FamilyHasProductOrdered whereProduct($value)
 * @mixin Eloquent
 */
class FamilyHasProductOrdered extends Model
{
    use HasFactory;

    protected $table = 'family_has_product_ordered';

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

    /**
     * Check if the cached data is fresh (updated within the last 24 hours)
     */
    public function isFresh(): bool
    {
        return $this->updated_at && $this->updated_at->diffInHours(Carbon::now()) < 24;
    }

    /**
     * Get the product data as a collection
     */
    public function getProductData(): \Illuminate\Support\Collection
    {
        return collect($this->product);
    }
}
