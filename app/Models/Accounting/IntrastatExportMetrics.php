<?php

/*
 * Author: Steven Wicca stewicalf@gmail.com
 * Created: Fri, 05 Dec 2025 14:25:47 Central Indonesia Time, Lembeng Beach, Bali, Indonesia
 * Copyright (c) 2025, Steven Wicca Alfredo
 */

namespace App\Models\Accounting;

use App\Enums\Accounting\Intrastat\IntrastatDeliveryTermsEnum;
use App\Enums\Accounting\Intrastat\IntrastatNatureOfTransactionEnum;
use App\Enums\Accounting\Intrastat\IntrastatTransportModeEnum;
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
 * @property string|null $delivery_note_type order or replacement
 * @property int $invoices_count Number of invoices (0 = replacements/samples)
 * @property array<array-key, mixed>|null $partner_tax_numbers Array of unique customer tax numbers with validation status
 * @property int $valid_tax_numbers_count
 * @property int $invalid_tax_numbers_count
 * @property IntrastatTransportModeEnum|null $mode_of_transport 1=SEA, 2=RAIL, 3=ROAD, 4=AIR, 5=POST, 7=PIPELINE, 8=INLAND_WATERWAY, 9=SELF_PROPULSION
 * @property IntrastatDeliveryTermsEnum|null $delivery_terms EXW, FOB, CIF, DAP, DDP, etc.
 * @property IntrastatNatureOfTransactionEnum|null $nature_of_transaction 11=Outright purchase/sale, 21=Return/replacement, etc.
 * @property-read Country $country
 * @property-read Organisation $organisation
 * @property-read TaxCategory|null $taxCategory
 * @method static Builder<static>|IntrastatExportMetrics newModelQuery()
 * @method static Builder<static>|IntrastatExportMetrics newQuery()
 * @method static Builder<static>|IntrastatExportMetrics query()
 * @mixin Eloquent
 */
class IntrastatExportMetrics extends Model
{
    protected $table = 'intrastat_export_metrics';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'date' => 'datetime',
            'data' => 'array',
            'partner_tax_numbers' => 'array',
            'mode_of_transport' => IntrastatTransportModeEnum::class,
            'delivery_terms' => IntrastatDeliveryTermsEnum::class,
            'nature_of_transaction' => IntrastatNatureOfTransactionEnum::class,
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
