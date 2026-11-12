<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 12 Nov 2026 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Models\SupplyChain;

use App\Models\Web\IpGeolocation;
use App\Models\Traits\InGroup;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

/**
 * The proof that a supplier accepted a version of the Supplier Agreement: who typed their name, for which company,
 * when the server received it, from where, and the hash of the exact text shown. Never changed or deleted.
 *
 * @property int $id
 * @property int $group_id
 * @property int $supplier_id
 * @property int $supplier_agreement_version_id
 * @property int|null $supplier_share_link_id
 * @property int|null $supplier_portal_profile_id
 * @property string $supplier_legal_name
 * @property string $authorised_person
 * @property string $position
 * @property \Illuminate\Support\Carbon $accepted_at
 * @property string|null $ip
 * @property string|null $country_code
 * @property int|null $ip_geolocation_id
 * @property string|null $user_agent
 * @property string|null $locale
 * @property string $body_hash
 * @property-read SupplierAgreementVersion $version
 * @property-read Supplier $supplier
 */
class SupplierAgreementAcceptance extends Model
{
    use InGroup;

    public const null UPDATED_AT = null;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'accepted_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::updating(function (SupplierAgreementAcceptance $acceptance) {
            if (array_diff(array_keys($acceptance->getDirty()), ['ip_geolocation_id']) !== []) {
                throw new LogicException('A supplier agreement acceptance cannot be changed');
            }
        });
        static::deleting(fn () => throw new LogicException('A supplier agreement acceptance cannot be deleted'));
    }

    public function version(): BelongsTo
    {
        return $this->belongsTo(SupplierAgreementVersion::class, 'supplier_agreement_version_id');
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function shareLink(): BelongsTo
    {
        return $this->belongsTo(SupplierShareLink::class, 'supplier_share_link_id');
    }

    public function ipGeolocation(): BelongsTo
    {
        return $this->belongsTo(IpGeolocation::class);
    }
}
