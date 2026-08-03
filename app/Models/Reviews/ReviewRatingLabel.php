<?php

namespace App\Models\Reviews;

use App\Enums\Catalogue\Review\ReviewContextEnum;
use App\Enums\Catalogue\Review\ReviewRatingDimensionEnum;
use App\Models\Catalogue\Shop;
use App\Models\SysAdmin\Group;
use App\Models\SysAdmin\Organisation;
use App\Models\Traits\InShop;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string $model_type
 * @property int $model_id
 * @property ReviewContextEnum $review_context
 * @property ReviewRatingDimensionEnum $dimension
 * @property string $label
 * @property string|null $description
 * @property int $sort_order
 * @property bool $is_active
 * @property bool $is_required
 * @property numeric $weight
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read Group|null $group
 * @property-read Organisation|null $organisation
 * @property-read Shop|null $shop
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ReviewRatingLabel newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ReviewRatingLabel newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ReviewRatingLabel query()
 * @mixin \Eloquent
 */
class ReviewRatingLabel extends Model
{
    use InShop;

    protected $guarded = [];

    protected $casts = [
        'review_context' => ReviewContextEnum::class,
        'dimension'      => ReviewRatingDimensionEnum::class,
        'is_active'      => 'boolean',
        'is_required'    => 'boolean',
        'weight'         => 'decimal:2',
    ];

    public static function dimensionOptions(): array
    {
        return ReviewRatingDimensionEnum::labels();
    }

    public static function reviewContextOptions(): array
    {
        return ReviewContextEnum::labels();
    }
}
