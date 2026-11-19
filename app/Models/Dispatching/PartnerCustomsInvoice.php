<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 19 Nov 2026 14:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Models\Dispatching;

use App\Models\Procurement\OrgPartner;
use App\Models\Traits\InOrganisation;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * One customs invoice of a truck to a partner, for one customs split (HELP-4482), frozen as issued.
 * A customs-only document: never an Invoice, never counted in sales or accounting.
 *
 * @property int $id
 * @property int $group_id
 * @property int $organisation_id
 * @property int $org_partner_id
 * @property int $partner_shipment_id
 * @property string $split main, cosmetic or gb
 * @property string $label printed title of the split, frozen in English
 * @property bool $with_origin_declaration prints the UK preferential origin declaration
 * @property string $reference from the partner's customs_invoice serial reference
 * @property \Illuminate\Support\Carbon $date
 * @property int $number_lines
 * @property string $units
 * @property string $amount
 * @property string $net_weight
 * @property string $gross_weight
 * @property-read PartnerShipment $partnerShipment
 * @property-read OrgPartner $orgPartner
 * @property-read \Illuminate\Database\Eloquent\Collection<int, PartnerCustomsInvoiceLine> $lines
 * @mixin \Eloquent
 */
class PartnerCustomsInvoice extends Model
{
    use InOrganisation;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'date'                    => 'date',
            'with_origin_declaration' => 'boolean',
            'units'        => 'decimal:3',
            'amount'       => 'decimal:2',
            'net_weight'   => 'decimal:3',
            'gross_weight' => 'decimal:3',
        ];
    }

    public function partnerShipment(): BelongsTo
    {
        return $this->belongsTo(PartnerShipment::class);
    }

    public function orgPartner(): BelongsTo
    {
        return $this->belongsTo(OrgPartner::class);
    }

    public function lines(): HasMany
    {
        return $this->hasMany(PartnerCustomsInvoiceLine::class)->orderBy('position');
    }
}
