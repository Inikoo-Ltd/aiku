<?php

namespace App\Models\Catalogue;

use App\Enums\Catalogue\Review\ReviewStatusEnum;
use App\Models\CRM\Customer;
use App\Models\Traits\HasHistory;
use App\Models\Traits\InShop;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use OwenIt\Auditing\Contracts\Auditable;
use App\Models\Ordering\Order;

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
class Review extends Model implements Auditable
{
    use InShop;
    use SoftDeletes;
    use HasHistory;

    protected $guarded = [];

    protected $casts = [
        'status'               => ReviewStatusEnum::class,
        'like_count'        => 'integer',
        'meta'                 => 'array',
    ];

    protected $attributes = [
        'meta' => '{}',
    ];

    public function reviewable(): MorphTo
    {
        return $this->morphTo();
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function media(): HasMany
    {
        return $this->hasMany(ReviewMedia::class);
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }
}
