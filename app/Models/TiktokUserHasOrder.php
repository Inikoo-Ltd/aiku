<?php

namespace App\Models;

use App\Enums\Dropshipping\ChannelFulfilmentStateEnum;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 *
 *
 * @property int $id
 * @property int $tiktok_user_id
 * @property string $model_type
 * @property int $model_id
 * @property ChannelFulfilmentStateEnum $state
 * @property int|null $tiktok_fulfilment_id
 * @property int|null $tiktok_order_id
 * @property int $customer_client_id
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read Model|\Eloquent $model
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TiktokUserHasOrder newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TiktokUserHasOrder newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TiktokUserHasOrder query()
 * @mixin \Eloquent
 */
class TiktokUserHasOrder extends Model
{
    protected $guarded = [];

    protected $casts = [
        'state' => ChannelFulfilmentStateEnum::class
    ];

    public function model(): MorphTo
    {
        return $this->morphTo('model');
    }
}
