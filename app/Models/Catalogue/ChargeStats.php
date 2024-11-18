<?php
/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 15 Jul 2024 13:36:12 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Models\Catalogue;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 *
 *
 * @property int $id
 * @property int $charge_id
 * @property int $number_historic_assets
 * @property string|null $first_used_at
 * @property string|null $last_used_at
 * @property int $number_customers
 * @property int $number_orders
 * @property int $number_invoices
 * @property int $number_delivery_notes
 * @property string $amount
 * @property string $org_amount
 * @property string $group_amount
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\Catalogue\Charge $charge
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ChargeStats newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ChargeStats newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ChargeStats query()
 * @mixin \Eloquent
 */
class ChargeStats extends Model
{
    protected $table = 'charge_stats';

    protected $guarded = [];

    public function charge(): BelongsTo
    {
        return $this->belongsTo(Charge::class);
    }
}
