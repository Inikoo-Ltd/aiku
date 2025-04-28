<?php

namespace App\Models;

use App\Enums\Dropshipping\ShopifyFulfilmentReasonEnum;
use App\Enums\Dropshipping\ChannelFulfilmentStateEnum;
use App\Models\Dropshipping\CustomerClient;
use App\Models\Dropshipping\ShopifyUser;
use App\Models\Ordering\Order;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\Relations\Pivot;

/**
 *
 *
 * @property int $id
 * @property int $shopify_user_id
 * @property int $model_id
 * @property int|null $shopify_fulfilment_id
 * @property int|null $shopify_order_id
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property string $model_type
 * @property ChannelFulfilmentStateEnum $state
 * @property int|null $customer_client_id
 * @property ShopifyFulfilmentReasonEnum|null $no_fulfilment_reason
 * @property string|null $no_fulfilment_reason_notes
 * @property-read CustomerClient|null $customerClient
 * @property-read \Illuminate\Database\Eloquent\Model|\Eloquent $model
 * @property-read ShopifyUser $shopifyUser
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ShopifyUserHasFulfilment newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ShopifyUserHasFulfilment newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ShopifyUserHasFulfilment query()
 * @mixin \Eloquent
 */
class ShopifyUserHasFulfilment extends Pivot
{
    protected $table = 'shopify_user_has_fulfilments';

    protected $casts = [
        'state' => ChannelFulfilmentStateEnum::class,
        'no_fulfilment_reason' => ShopifyFulfilmentReasonEnum::class
    ];

    public function shopifyUser(): BelongsTo
    {
        return $this->belongsTo(ShopifyUser::class);
    }

    public function customerClient(): BelongsTo
    {
        return $this->belongsTo(CustomerClient::class);
    }

    public function model(): MorphTo
    {
        return $this->morphTo('model');
    }
}
