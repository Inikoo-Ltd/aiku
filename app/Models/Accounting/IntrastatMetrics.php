<?php

/*
 * Author: Steven Wicca stewicalf@gmail.com
 * Created: Fri, 05 Dec 2025 14:25:47 Central Indonesia Time, Lembeng Beach, Bali, Indonesia
 * Copyright (c) 2025, Steven Wicca Alfredo
 */

namespace App\Models\Accounting;

use App\Models\Helpers\Country;
use App\Models\Helpers\TaxCategory;
use App\Models\SysAdmin\Organisation;
use Eloquent;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $organisation_id
 * @property Carbon $date
 * @property string $tariff_code Tariff code (HS code), may contain spaces
 * @property int $country_id
 * @property int|null $tax_category_id
 * @property string $quantity
 * @property string $value_org_currency
 * @property int $weight Weight in grams
 * @property int $delivery_notes_count
 * @property int $products_count
 * @property array<array-key, mixed>|null $data Warnings, metadata
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Country $country
 * @property-read Organisation $organisation
 * @property-read TaxCategory|null $taxCategory
 * @method static Builder<static>|IntrastatMetrics newModelQuery()
 * @method static Builder<static>|IntrastatMetrics newQuery()
 * @method static Builder<static>|IntrastatMetrics query()
 * @mixin Eloquent
 */
class IntrastatMetrics extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'date' => 'datetime',
            'data' => 'array'
        ];
    }

    public function organisation(): BelongsTo
    {
        return $this->belongsTo(Organisation::class);
    }

    public function country(): BelongsTo
    {
        return $this->belongsTo(Country::class);
    }

    public function taxCategory(): BelongsTo
    {
        return $this->belongsTo(TaxCategory::class);
    }
}
