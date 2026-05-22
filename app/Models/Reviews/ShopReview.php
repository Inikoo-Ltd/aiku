<?php

/*
 * author Louis Perez
 * created on 13-04-2026-09h-36m
 * github: https://github.com/louis-perez
 * copyright 2026
*/

namespace App\Models\Reviews;

use App\Enums\Catalogue\Review\ReviewStatusEnum;
use App\Models\CRM\Customer;
use App\Models\Traits\HasHistory;
use App\Models\Traits\InShop;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use OwenIt\Auditing\Contracts\Auditable;
use App\Models\Ordering\Order;
use App\Models\Reviews\Traits\IsReviews;
use App\Models\Traits\HasImage;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\MediaLibrary\HasMedia;

/**
 * @property int $id
 * @property int $group_id
 * @property int $organisation_id
 * @property int|null $shop_id
 * @property int|null $customer_id
 * @property int|null $order_id
 * @property int $rating_main
 * @property int $rating_a
 * @property int $rating_b
 * @property int $rating_c
 * @property int $rating_d
 * @property int $rating_e
 * @property \Illuminate\Support\Carbon|null $show_after
 * @property ReviewStatusEnum $status
 * @property string|null $title
 * @property string|null $message
 * @property int $like_count
 * @property array<array-key, mixed> $meta
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property \Illuminate\Support\Carbon|null $deleted_at
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Helpers\Audit> $audits
 * @property-read Customer|null $customer
 * @property-read \App\Models\SysAdmin\Group $group
 * @property-read \App\Models\Helpers\Media|null $image
 * @property-read \Spatie\MediaLibrary\MediaCollections\Models\Collections\MediaCollection<int, \App\Models\Helpers\Media> $images
 * @property-read \Spatie\MediaLibrary\MediaCollections\Models\Collections\MediaCollection<int, \App\Models\Helpers\Media> $media
 * @property-read Order|null $order
 * @property-read \App\Models\SysAdmin\Organisation $organisation
 * @property-read \App\Models\Helpers\Media|null $seoImage
 * @property-read \App\Models\Catalogue\Shop|null $shop
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ShopReview newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ShopReview newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ShopReview onlyTrashed()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ShopReview query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ShopReview withTrashed(bool $withTrashed = true)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ShopReview withoutTrashed()
 * @mixin \Eloquent
 */
class ShopReview extends Model implements Auditable, HasMedia
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

    public function replies(): HasMany
    {
        return $this->hasMany(ReviewReply::class, 'reviewable_id')
            ->where('review_replies.reviewable_type', $this->getTable());
    }
}
