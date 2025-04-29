<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 20 Jun 2023 20:26:47 Malaysia Time, Pantai Lembeng, Bali, Id
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Models\Ordering;

use Eloquent;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * App\Models\Ordering\OrderStats
 *
 * @property int $id
 * @property int $order_id
 * @property int $number_item_transactions_out_of_stock_in_basket transactions at the time up submission from basket
 * @property string|null $out_of_stock_in_basket_grp_net_amount
 * @property string|null $out_of_stock_in_basket_org_net_amount
 * @property string $out_of_stock_in_basket_net_amount
 * @property int $number_item_transactions_at_submission transactions at the time up submission from basket
 * @property int $number_created_item_transactions_after_submission
 * @property int $number_updated_item_transactions_after_submission
 * @property int $number_deleted_item_transactions_after_submission
 * @property int $number_item_transactions transactions including cancelled
 * @property int $number_current_item_transactions transactions excluding cancelled
 * @property int $number_item_transactions_state_creating
 * @property int $number_item_transactions_state_submitted
 * @property int $number_item_transactions_state_in_warehouse
 * @property int $number_item_transactions_state_handling
 * @property int $number_item_transactions_state_packed
 * @property int $number_item_transactions_state_finalised
 * @property int $number_item_transactions_state_dispatched
 * @property int $number_item_transactions_state_cancelled
 * @property int $number_item_transactions_status_creating
 * @property int $number_item_transactions_status_processing
 * @property int $number_item_transactions_status_settled
 * @property int $number_offer_campaigns
 * @property int $number_offers
 * @property int $number_offer_components
 * @property int $number_transactions_with_offers
 * @property string $discounts_amount from % offs
 * @property string $giveaways_value_amount Value of goods given for free
 * @property string $cashback_amount
 * @property string|null $org_giveaways_value_amount
 * @property string|null $org_cashback_amount
 * @property string|null $org_discounts_amount
 * @property string|null $grp_discounts_amount
 * @property string|null $grp_giveaways_value_amount
 * @property string|null $grp_cashback_amount
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\Ordering\Order $order
 * @method static Builder<static>|OrderStats newModelQuery()
 * @method static Builder<static>|OrderStats newQuery()
 * @method static Builder<static>|OrderStats query()
 * @mixin Eloquent
 */
class OrderStats extends Model
{
    protected $table = 'order_stats';

    protected $guarded = [];


    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }
}
