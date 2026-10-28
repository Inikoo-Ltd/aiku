<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 28 Oct 2026 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Models\SupplyChain;

use App\Models\Traits\HasImage;
use App\Models\Traits\InGroup;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\MediaLibrary\HasMedia;

/**
 * The person looking at a supplier page: the email that logs in by itself, or whoever was given a direct link,
 * recognised again by a cookie. Their own name, phone, photo and colours, shown only on the supplier page.
 *
 * @property int $id
 * @property string $ulid
 * @property int $supplier_id
 * @property int|null $supplier_portal_contact_id
 * @property int|null $supplier_share_link_id
 * @property string|null $name
 * @property string|null $phone
 * @property int $theme
 * @property int|null $image_id
 */
class SupplierPortalProfile extends Model implements HasMedia
{
    use HasImage;
    use HasUlids;
    use InGroup;

    protected $guarded = [];

    /**
     * @return array<int, string>
     */
    public function uniqueIds(): array
    {
        return ['ulid'];
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function portalContact(): BelongsTo
    {
        return $this->belongsTo(SupplierPortalContact::class, 'supplier_portal_contact_id');
    }
}
