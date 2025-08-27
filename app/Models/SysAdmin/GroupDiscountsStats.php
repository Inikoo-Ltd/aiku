<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 11 Sept 2024 21:28:45 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Models\SysAdmin;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $group_id
 * @property int $number_customers
 * @property int $number_orders
 * @property int $number_invoices
 * @property int $number_delivery_notes
 * @property string $grp_amount
 * @property int $number_offer_campaigns
 * @property int $number_current_offer_campaigns
 * @property int $number_offer_campaigns_state_in_process
 * @property int $number_offer_campaigns_state_active
 * @property int $number_offer_campaigns_state_finished
 * @property int $number_offer_campaigns_state_suspended
 * @property int $number_offers
 * @property int $number_current_offers
 * @property int $number_offers_state_in_process
 * @property int $number_offers_state_active
 * @property int $number_offers_state_finished
 * @property int $number_offers_state_suspended
 * @property int $number_offer_components
 * @property int $number_current_offer_components
 * @property int $number_offer_components_state_in_process
 * @property int $number_offer_components_state_active
 * @property int $number_offer_components_state_finished
 * @property int $number_offer_components_state_suspended
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\SysAdmin\Group $group
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GroupDiscountsStats newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GroupDiscountsStats newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GroupDiscountsStats query()
 * @mixin \Eloquent
 */
class GroupDiscountsStats extends Model
{
    protected $table = 'group_discounts_stats';

    protected $guarded = [];

    public function group(): BelongsTo
    {
        return $this->belongsTo(Group::class);
    }
}
