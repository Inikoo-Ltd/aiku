<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 04 Nov 2026 14:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Models\Dispatching;

use App\Models\Procurement\OrgPartner;
use App\Models\Traits\InOrganisation;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * One truck of partner orders (HELP-4482). Its customs invoices are numbered
 * prefix + first_number, first_number + 1, ... one per split (cosmetic, GB origin, rest) that has lines.
 *
 * @property int $id
 * @property int $group_id
 * @property int $organisation_id
 * @property int $org_partner_id
 * @property string $prefix
 * @property int $first_number
 * @property array<int, string> $splits one customs invoice per split, numbered in this order
 * @property \Illuminate\Support\Carbon $date
 * @property int|null $number_pallets
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \Illuminate\Database\Eloquent\Collection<int, DeliveryNote> $deliveryNotes
 * @property-read OrgPartner $orgPartner
 * @mixin \Eloquent
 */
class PartnerShipment extends Model
{
    use InOrganisation;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'date'   => 'date',
            'splits' => 'array',
        ];
    }

    public function orgPartner(): BelongsTo
    {
        return $this->belongsTo(OrgPartner::class);
    }

    public function deliveryNotes(): BelongsToMany
    {
        return $this->belongsToMany(DeliveryNote::class, 'partner_shipment_delivery_notes');
    }

    public function documentReference(int $index): string
    {
        return $this->prefix.str_pad((string) ($this->first_number + $index), 4, '0', STR_PAD_LEFT);
    }
}
