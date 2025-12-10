<?php

namespace App\Models\Catalogue;

use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property int $variant_id
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @method static \Illuminate\Database\Eloquent\Builder<static>|VariantSalesStats newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|VariantSalesStats newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|VariantSalesStats query()
 * @mixin \Eloquent
 */
class VariantSalesStats extends Model
{
}
