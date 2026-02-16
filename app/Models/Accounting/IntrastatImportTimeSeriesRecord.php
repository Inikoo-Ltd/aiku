<?php

/*
 * Author: Steven Wicca <stewicalf@gmail.com>
 * Created: Thu, 13 Feb 2026 16:16:00 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2026, Steven Wicca Alfredo
 */

namespace App\Models\Accounting;

use App\Enums\Accounting\Intrastat\IntrastatDeliveryTermsEnum;
use App\Enums\Accounting\Intrastat\IntrastatNatureOfTransactionEnum;
use App\Enums\Accounting\Intrastat\IntrastatTransportModeEnum;
use App\Models\SysAdmin\Organisation;
use Eloquent;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $intrastat_import_time_series_id
 * @property int $organisation_id
 * @property string $frequency
 * @property string $quantity
 * @property string $value_org_currency
 * @property int $weight
 * @property int $supplier_deliveries_count
 * @property int $parts_count
 * @property int $invoices_count
 * @property array|null $supplier_tax_numbers
 * @property int $valid_tax_numbers_count
 * @property int $invalid_tax_numbers_count
 * @property string|null $mode_of_transport
 * @property string|null $delivery_terms
 * @property string|null $nature_of_transaction
 * @property Carbon|null $from
 * @property Carbon|null $to
 * @property string|null $period
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read IntrastatImportTimeSeries $intrastatImportTimeSeries
 * @property-read Organisation $organisation
 * @method static Builder<static>|IntrastatImportTimeSeriesRecord newModelQuery()
 * @method static Builder<static>|IntrastatImportTimeSeriesRecord newQuery()
 * @method static Builder<static>|IntrastatImportTimeSeriesRecord query()
 * @mixin Eloquent
 */
class IntrastatImportTimeSeriesRecord extends Model
{
    protected $table = 'intrastat_import_time_series_records';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'from'                  => 'datetime',
            'to'                    => 'datetime',
            'supplier_tax_numbers'  => 'array',
            'mode_of_transport'     => IntrastatTransportModeEnum::class,
            'delivery_terms'        => IntrastatDeliveryTermsEnum::class,
            'nature_of_transaction' => IntrastatNatureOfTransactionEnum::class,
        ];
    }

    public function intrastatImportTimeSeries(): BelongsTo
    {
        return $this->belongsTo(IntrastatImportTimeSeries::class);
    }

    public function organisation(): BelongsTo
    {
        return $this->belongsTo(Organisation::class);
    }
}
