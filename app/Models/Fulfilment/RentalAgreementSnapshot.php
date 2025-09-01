<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 26 Apr 2024 16:29:54 British Summer Time, Sheffield, UK
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Models\Fulfilment;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $rental_agreement_id
 * @property array<array-key, mixed> $data
 * @property bool $is_first_snapshot
 * @property int $clauses_added
 * @property int $clauses_removed
 * @property int $clauses_updated
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\Fulfilment\RentalAgreement $rentalAgreement
 * @method static \Illuminate\Database\Eloquent\Builder<static>|RentalAgreementSnapshot newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|RentalAgreementSnapshot newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|RentalAgreementSnapshot query()
 * @mixin \Eloquent
 */
class RentalAgreementSnapshot extends Model
{
    protected $guarded = [];

    protected $casts = [
        'data'                        => 'array',
    ];


    protected $attributes = [
        'data'           => '{}',
    ];

    public function rentalAgreement(): BelongsTo
    {
        return $this->belongsTo(RentalAgreement::class);
    }
}
