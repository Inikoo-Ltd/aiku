<?php

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
 * @property string $tariff_code Part tariff code
 * @property int $country_id Supplier country
 * @property int|null $tax_category_id
 * @property string $quantity
 * @property string $value_org_currency
 * @property int $weight Weight in grams
 * @property int $supplier_deliveries_count
 * @property int $parts_count
 * @property int $invoices_count
 * @property array|null $supplier_tax_numbers Array of unique supplier tax numbers with validation status
 * @property int $valid_tax_numbers_count
 * @property int $invalid_tax_numbers_count
 * @property IntrastatTransportModeEnum|null $mode_of_transport
 * @property IntrastatDeliveryTermsEnum|null $delivery_terms
 * @property IntrastatNatureOfTransactionEnum|null $nature_of_transaction
 * @property array<array-key, mixed>|null $data Warnings, metadata
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Country $country
 * @property-read Organisation $organisation
 * @property-read TaxCategory|null $taxCategory
 * @method static Builder<static>|IntrastatImportMetrics newModelQuery()
 * @method static Builder<static>|IntrastatImportMetrics newQuery()
 * @method static Builder<static>|IntrastatImportMetrics query()
 * @mixin Eloquent
 */
class IntrastatImportMetrics extends Model
{
    protected $table = 'intrastat_import_metrics';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'date' => 'datetime',
            'data' => 'array',
            'supplier_tax_numbers' => 'array',
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
