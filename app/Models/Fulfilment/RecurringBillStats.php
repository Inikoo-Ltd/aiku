<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 28 Apr 2024 12:54:58 British Summer Time, Sheffield, UK
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Models\Fulfilment;

use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property int $recurring_bill_id
 * @property int $number_transactions
 * @property int $number_transactions_type_pallets
 * @property int $number_transactions_type_stored_items
 * @property int $number_transactions_type_services
 * @property int $number_transactions_type_products
 * @property int $number_pallets
 * @property int $number_stored_items
 * @property int $number_pallet_deliveries
 * @property int $number_pallet_returns
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property int $number_transactions_type_spaces
 * @method static \Illuminate\Database\Eloquent\Builder<static>|RecurringBillStats newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|RecurringBillStats newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|RecurringBillStats query()
 * @mixin \Eloquent
 */
class RecurringBillStats extends Model
{
    protected $guarded = [];
}
