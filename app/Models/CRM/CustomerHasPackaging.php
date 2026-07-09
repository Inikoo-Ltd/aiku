<?php

/*
 * Author: Andi Ferdiawan
 * Created: Wed, 08 Jul 2026 16:40:00 Central Indonesia Time, Bali, Indonesia
 * Copyright (c) 2026, Inikoo Ltd
 */

namespace App\Models\CRM;

use App\Models\Billables\ModelHasLeaflet;
use App\Models\Billables\Packaging;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/**
 * @property int $id
 * @property int $customer_id
 * @property int|null $packaging_id
 * @property numeric|null $price Customer specific price, null means use packaging price
 * @property string|null $personalised_message
 * @property array<array-key, mixed> $data
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read Customer $customer
 * @property-read \Illuminate\Database\Eloquent\Collection<int, ModelHasLeaflet> $leaflets
 * @property-read Packaging|null $packaging
 * @mixin \Eloquent
 */
class CustomerHasPackaging extends Model
{
    protected $table = 'customer_has_packagings';

    protected $guarded = [];

    protected $casts = [
        'price' => 'decimal:2',
        'data'  => 'array',
    ];

    protected $attributes = [
        'data' => '{}',
    ];

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function packaging(): BelongsTo
    {
        return $this->belongsTo(Packaging::class);
    }

    public function leaflets(): MorphMany
    {
        return $this->morphMany(ModelHasLeaflet::class, 'model');
    }
}
