<?php

/*
 * author Louis Perez
 * created on 13-04-2026-09h-36m
 * github: https://github.com/louis-perez
 * copyright 2026
*/

namespace App\Models\Reviews;

use App\Enums\Catalogue\Review\ReviewStatusEnum;
use App\Models\Catalogue\Product;
use App\Models\CRM\Customer;
use App\Models\Masters\MasterAsset;
use App\Models\Traits\HasHistory;
use App\Models\Traits\InShop;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use OwenIt\Auditing\Contracts\Auditable;
use App\Models\Ordering\Order;
use App\Models\Reviews\Traits\IsReviews;
use App\Models\Traits\HasImage;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\MediaLibrary\HasMedia;

/**
 * @property int $id
 * @property int $group_id
 * @property int $organisation_id
 * @property int|null $shop_id
 * @property int|null $customer_id
 * @property string $reviewable_type
 * @property int $reviewable_id
 * @property ReviewStatusEnum $status
 * @property int $rating
 * @property string|null $message
 * @property int $like_count
 * @property array<array-key, mixed> $meta
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property \Illuminate\Support\Carbon|null $deleted_at
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Helpers\Audit> $audits
 * @property-read Customer|null $customer
 * @property-read \App\Models\SysAdmin\Group $group
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Catalogue\ReviewMedia> $media
 * @property-read Order|null $order
 * @property-read \App\Models\SysAdmin\Organisation $organisation
 * @property-read Model|\Eloquent $reviewable
 * @property-read \App\Models\Catalogue\Shop|null $shop
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Review newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Review newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Review onlyTrashed()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Review query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Review withTrashed(bool $withTrashed = true)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Review withoutTrashed()
 * @mixin \Eloquent
 */
class ProductReview extends Model implements Auditable, HasMedia
{
    use InShop;
    use SoftDeletes;
    use HasHistory;
    use HasImage;
    use IsReviews;

    protected $guarded = [];

    protected $casts = [
        'status'                => ReviewStatusEnum::class,
        'like_count'            => 'integer',
        'meta'                  => 'array',
        'show_after'            => 'datetime'
    ];

    protected $attributes = [
        'meta' => '{}',
    ];


    public function getRouteKeyName(): string
    {
        return 'id';
    }

    protected static function booted(): void
    {
        static::saving(function (ShopReview $model) {
            $model->calculateAverageRating();
        });
    }

    public function masterProduct(): BelongsTo
    {
        return $this->belongsTo(MasterAsset::class, 'master_product_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'product_id');
    }
}
