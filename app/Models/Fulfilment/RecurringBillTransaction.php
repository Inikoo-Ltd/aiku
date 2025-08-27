<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 28 Apr 2024 08:02:54 British Summer Time, Sheffield, UK
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Models\Fulfilment;

use App\Models\Catalogue\Asset;
use App\Models\Catalogue\HistoricAsset;
use App\Models\Traits\InFulfilmentCustomer;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property int $id
 * @property int $group_id
 * @property int $organisation_id
 * @property int $recurring_bill_id
 * @property int $fulfilment_id
 * @property int $fulfilment_customer_id
 * @property \Illuminate\Support\Carbon $start_date
 * @property \Illuminate\Support\Carbon|null $end_date
 * @property string|null $item_type
 * @property int|null $item_id
 * @property int $asset_id
 * @property int $historic_asset_id
 * @property int|null $rental_agreement_clause_id
 * @property string $quantity
 * @property string $gross_amount net amount before discounts
 * @property string $net_amount
 * @property string|null $grp_net_amount
 * @property string|null $org_net_amount
 * @property int $tax_category_id
 * @property array<array-key, mixed> $data
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property \Illuminate\Support\Carbon|null $deleted_at
 * @property string $unit_cost
 * @property string $discount_percentage
 * @property string $temporal_quantity
 * @property int|null $pallet_delivery_id
 * @property int|null $pallet_return_id
 * @property int|null $fulfilment_transaction_id
 * @property-read Asset $asset
 * @property-read \App\Models\Fulfilment\RentalAgreementClause|null $clause
 * @property-read \App\Models\Fulfilment\Fulfilment $fulfilment
 * @property-read \App\Models\Fulfilment\FulfilmentCustomer $fulfilmentCustomer
 * @property-read \App\Models\Fulfilment\FulfilmentTransaction|null $fulfilmentTransaction
 * @property-read \App\Models\SysAdmin\Group $group
 * @property-read HistoricAsset $historicAsset
 * @property-read Model|\Eloquent|null $item
 * @property-read \App\Models\SysAdmin\Organisation $organisation
 * @property-read \App\Models\Fulfilment\RecurringBill $recurringBill
 * @method static \Illuminate\Database\Eloquent\Builder<static>|RecurringBillTransaction newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|RecurringBillTransaction newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|RecurringBillTransaction onlyTrashed()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|RecurringBillTransaction query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|RecurringBillTransaction withTrashed(bool $withTrashed = true)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|RecurringBillTransaction withoutTrashed()
 * @mixin \Eloquent
 */
class RecurringBillTransaction extends Model
{
    use SoftDeletes;
    use inFulfilmentCustomer;

    protected $table = 'recurring_bill_transactions';

    protected $casts = [
        'data' => 'array',
        'start_date' => 'datetime',
        'end_date'   => 'datetime',
    ];

    protected $attributes = [
        'data' => '{}',
    ];

    protected $guarded = [];

    public function item(): MorphTo
    {
        return $this->morphTo();
    }

    public function recurringBill(): BelongsTo
    {
        return $this->belongsTo(RecurringBill::class);
    }

    public function historicAsset(): BelongsTo
    {
        return $this->belongsTo(HistoricAsset::class);
    }

    public function asset(): BelongsTo
    {
        return $this->belongsTo(Asset::class);
    }

    public function clause(): BelongsTo
    {
        return $this->belongsTo(RentalAgreementClause::class, 'rental_agreement_clause_id');
    }

    public function fulfilmentTransaction(): BelongsTo
    {
        return $this->belongsTo(FulfilmentTransaction::class);
    }

}
