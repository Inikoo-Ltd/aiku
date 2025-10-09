<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 26 Apr 2024 16:29:54 British Summer Time, Sheffield, UK
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Models\Fulfilment;

use App\Enums\Fulfilment\RentalAgreementClause\RentalAgreementCauseStateEnum;
use App\Enums\Fulfilment\RentalAgreementClause\RentalAgreementClauseTypeEnum;
use App\Models\Catalogue\Asset;
use App\Models\Traits\InFulfilmentCustomer;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property int $id
 * @property int $group_id
 * @property int $organisation_id
 * @property int $fulfilment_id
 * @property int $fulfilment_customer_id
 * @property RentalAgreementCauseStateEnum $state
 * @property RentalAgreementClauseTypeEnum $type
 * @property int $asset_id
 * @property int $rental_agreement_id
 * @property int|null $rental_agreement_snapshot_id
 * @property string $percentage_off
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property \Illuminate\Support\Carbon|null $deleted_at
 * @property-read Asset $asset
 * @property-read \App\Models\Fulfilment\Fulfilment $fulfilment
 * @property-read \App\Models\Fulfilment\FulfilmentCustomer $fulfilmentCustomer
 * @property-read \App\Models\SysAdmin\Group $group
 * @property-read \App\Models\SysAdmin\Organisation $organisation
 * @property-read \App\Models\Fulfilment\RentalAgreement $rentalAgreement
 * @method static \Illuminate\Database\Eloquent\Builder<static>|RentalAgreementClause newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|RentalAgreementClause newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|RentalAgreementClause onlyTrashed()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|RentalAgreementClause query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|RentalAgreementClause withTrashed(bool $withTrashed = true)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|RentalAgreementClause withoutTrashed()
 * @mixin \Eloquent
 */
class RentalAgreementClause extends Model
{
    use InFulfilmentCustomer;
    use SoftDeletes;

    protected $guarded = [];

    protected $casts = [
        'type'  => RentalAgreementClauseTypeEnum::class,
        'state' => RentalAgreementCauseStateEnum::class
    ];


    public function asset(): BelongsTo
    {
        return $this->belongsTo(Asset::class);
    }

    public function rentalAgreement(): BelongsTo
    {
        return $this->belongsTo(RentalAgreement::class);
    }



}
