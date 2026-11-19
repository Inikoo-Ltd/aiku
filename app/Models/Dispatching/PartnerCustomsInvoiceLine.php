<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 19 Nov 2026 14:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Models\Dispatching;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A line of a customs invoice as issued: one tariff code and origin (HELP-4482).
 *
 * @property int $id
 * @property int $partner_customs_invoice_id
 * @property int $position
 * @property string|null $tariff_code
 * @property string|null $origin
 * @property string|null $parts
 * @property string|null $description
 * @property string|null $local_description
 * @property string|null $un_numbers
 * @property string $units
 * @property string $amount
 * @property string $net_weight
 * @property string $gross_weight
 * @mixin \Eloquent
 */
class PartnerCustomsInvoiceLine extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'units'        => 'decimal:3',
            'amount'       => 'decimal:2',
            'net_weight'   => 'decimal:3',
            'gross_weight' => 'decimal:3',
        ];
    }

    public function customsInvoice(): BelongsTo
    {
        return $this->belongsTo(PartnerCustomsInvoice::class, 'partner_customs_invoice_id');
    }
}
