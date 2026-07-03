<?php

/*
 * author Louis Perez
 * created on 13-04-2026-09h-36m
 * GitHub: https://github.com/louis-perez
 * copyright 2026
*/

namespace App\Models\Reviews;

use App\Enums\Catalogue\Review\ReviewStateEnum;
use App\Enums\Catalogue\Review\ReviewStatusEnum;
use App\Enums\Catalogue\Review\ReviewScopeEnum;
use App\Models\Catalogue\Product;
use App\Models\Catalogue\ProductCategory;
use App\Models\CRM\Customer;
use App\Models\Masters\MasterAsset;
use App\Models\Masters\MasterProductCategory;
use App\Models\Traits\HasHistory;
use App\Models\Traits\InShop;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
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
 * @property ReviewStateEnum $state
 * @property bool $is_online
 * @property int|null $shop_id
 * @property int|null $customer_id
 * @property ReviewScopeEnum $scope
 * @property int|null $order_id
 * @property int|null $master_product_category_id
 * @property int|null $product_category_id
 * @property int|null $master_product_id
 * @property int|null $product_id
 * @property numeric $rating_main
 * @property int|null $rating_a
 * @property int|null $rating_b
 * @property int|null $rating_c
 * @property int|null $rating_d
 * @property int|null $rating_e
 * @property \Illuminate\Support\Carbon|null $auto_approve_at
 * @property bool $is_public
 * @property string|null $message
 * @property array<array-key, mixed> $web_images
 * @property int|null $language_id
 * @property ReviewStatusEnum $review_status
 * @property bool $approved
 * @property bool $auto_approved
 * @property int|null $approved_by
 * @property \Illuminate\Support\Carbon|null $published_at
 * @property bool $removed
 * @property int|null $removed_by
 * @property \Illuminate\Support\Carbon|null $removed_at
 * @property string|null $removed_reason
 * @property bool $replied
 * @property string|null $reply_message
 * @property \Illuminate\Support\Carbon|null $reply_at
 * @property int|null $reply_by
 * @property int $likes
 * @property int $dislikes
 * @property int $replay_likes
 * @property int $replay_dislikes
 * @property string|null $external_id
 * @property array<array-key, mixed> $meta
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property \Illuminate\Support\Carbon|null $deleted_at
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Helpers\Audit> $audits
 * @property-read Customer|null $customer
 * @property-read \App\Models\SysAdmin\Group|null $group
 * @property-read \App\Models\Helpers\Media|null $image
 * @property-read \Spatie\MediaLibrary\MediaCollections\Models\Collections\MediaCollection<int, \App\Models\Helpers\Media> $images
 * @property-read MasterAsset|null $masterProduct
 * @property-read MasterProductCategory|null $masterProductCategory
 * @property-read \Spatie\MediaLibrary\MediaCollections\Models\Collections\MediaCollection<int, \App\Models\Helpers\Media> $media
 * @property-read Order|null $order
 * @property-read \App\Models\SysAdmin\Organisation $organisation
 * @property-read Product|null $product
 * @property-read ProductCategory|null $productCategory
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Reviews\ReviewReaction> $reactions
 * @property-read \App\Models\Helpers\Media|null $seoImage
 * @property-read \App\Models\Catalogue\Shop|null $shop
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Review newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Review newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Review onlyTrashed()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Review query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Review withTrashed(bool $withTrashed = true)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Review withoutTrashed()
 * @mixin \Eloquent
 */
class Review extends Model implements Auditable, HasMedia
{
    use InShop;
    use SoftDeletes;
    use HasHistory;
    use HasImage;
    use IsReviews;

    protected $guarded = [];

    protected $casts = [
        'scope'             => ReviewScopeEnum::class,
        'state'             => ReviewStateEnum::class,
        'review_status'     => ReviewStatusEnum::class,
        'likes'             => 'integer',
        'dislikes'          => 'integer',
        'replay_likes'      => 'integer',
        'replay_dislikes'   => 'integer',
        'meta'              => 'array',
        'web_images'        => 'array',
        'auto_approve_at'   => 'datetime',
        'published_at'      => 'datetime',
        'removed_at'        => 'datetime',
        'reply_at'          => 'datetime',
    ];

    protected $attributes = [
        'meta'              => '{}',
        'web_images'        => '{}',
    ];

    public function getRouteKeyName(): string
    {
        return 'id';
    }

    protected static function booted(): void
    {
        static::saving(function (Review $model) {
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

    public function masterProductCategory(): BelongsTo
    {
        return $this->belongsTo(MasterProductCategory::class, 'master_product_category_id');
    }

    public function productCategory(): BelongsTo
    {
        return $this->belongsTo(ProductCategory::class, 'product_category_id');
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function reactions(): HasMany
    {
        return $this->hasMany(ReviewReaction::class);
    }
}
