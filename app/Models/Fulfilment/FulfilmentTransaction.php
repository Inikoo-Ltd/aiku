<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 09 Jun 2024 13:39:14 Central European Summer Time, Plane Abu Dhabi - Kuala Lumpur
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Models\Fulfilment;

use App\Enums\Fulfilment\FulfilmentTransaction\FulfilmentTransactionTypeEnum;
use App\Models\Catalogue\Asset;
use App\Models\Catalogue\HistoricAsset;
use App\Models\Traits\InFulfilment;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * @property int $id
 * @property int $group_id
 * @property int $organisation_id
 * @property string $parent_type
 * @property int $parent_id
 * @property int|null $fulfilment_id
 * @property int|null $fulfilment_customer_id
 * @property FulfilmentTransactionTypeEnum $type
 * @property int $asset_id
 * @property int $historic_asset_id
 * @property int|null $rental_agreement_clause_id
 * @property string $quantity
 * @property string $gross_amount net amount before discounts
 * @property string $net_amount
 * @property string|null $grp_net_amount
 * @property string|null $org_net_amount
 * @property int $tax_category_id
 * @property string|null $grp_exchange
 * @property string|null $org_exchange
 * @property bool $is_auto_assign
 * @property array<array-key, mixed> $data
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read Asset $asset
 * @property-read \App\Models\Fulfilment\RentalAgreementClause|null $clause
 * @property-read \App\Models\Fulfilment\Fulfilment|null $fulfilment
 * @property-read \App\Models\Fulfilment\FulfilmentCustomer|null $fulfilmentCustomer
 * @property-read \App\Models\SysAdmin\Group $group
 * @property-read HistoricAsset $historicAsset
 * @property-read \App\Models\SysAdmin\Organisation $organisation
 * @property-read Model|\Eloquent $parent
 * @property-read \App\Models\Fulfilment\RecurringBillTransaction|null $recurringBillTransaction
 * @method static \Illuminate\Database\Eloquent\Builder<static>|FulfilmentTransaction newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|FulfilmentTransaction newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|FulfilmentTransaction query()
 * @mixin \Eloquent
 */
class FulfilmentTransaction extends Model
{
    use HasFactory;
    use InFulfilment;


    protected $casts = [
        'data'             => 'array',
        'quantity_ordered' => 'decimal:3',
        'type'             => FulfilmentTransactionTypeEnum::class
    ];

    protected $attributes = [
        'data' => '{}',
    ];

    protected $guarded = [];

    public function parent(): MorphTo
    {
        return $this->morphTo();
    }

    public function asset(): BelongsTo
    {
        return $this->belongsTo(Asset::class);
    }

    public function historicAsset(): BelongsTo
    {
        return $this->belongsTo(HistoricAsset::class);
    }

    public function clause(): BelongsTo
    {
        return $this->belongsTo(RentalAgreementClause::class, 'rental_agreement_clause_id');
    }

    public function fulfilmentCustomer(): BelongsTo
    {
        return $this->belongsTo(FulfilmentCustomer::class);
    }

    public function recurringBillTransaction(): HasOne
    {
        return $this->hasOne(RecurringBillTransaction::class);
    }
}
