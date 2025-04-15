<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 *
 *
 * @property int $id
 * @property int $tiktok_user_id
 * @property string $productable_type
 * @property int $productable_id
 * @property string $tiktok_product_id
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property int|null $portfolio_id
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TiktokUserHasProduct newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TiktokUserHasProduct newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TiktokUserHasProduct query()
 * @mixin \Eloquent
 */
class TiktokUserHasProduct extends Model
{
    protected $guarded = [];
}
