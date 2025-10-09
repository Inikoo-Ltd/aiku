<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 15 Jun 2024 00:37:42 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Models\Fulfilment;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $rental_agreement_id
 * @property int $number_rental_agreement_snapshots
 * @property int $number_rental_agreement_clauses
 * @property int $number_rental_agreement_clauses_type_product
 * @property int $number_rental_agreement_clauses_type_service
 * @property int $number_rental_agreement_clauses_type_rental
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\Fulfilment\RentalAgreement $rentalAgreement
 * @method static \Illuminate\Database\Eloquent\Builder<static>|RentalAgreementStats newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|RentalAgreementStats newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|RentalAgreementStats query()
 * @mixin \Eloquent
 */
class RentalAgreementStats extends Model
{
    protected $guarded = [];

    public function rentalAgreement(): BelongsTo
    {
        return $this->belongsTo(RentalAgreement::class);
    }
}
