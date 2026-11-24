<?php

namespace App\Models\Masters;

use App\Models\Helpers\Currency;
use App\Models\Procurement\OrgPartner;
use App\Models\Traits\HasHistory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use OwenIt\Auditing\Contracts\Auditable;

/**
 * The price per outer a partner organisation pays the selling organisation for a master product,
 * agreed by hand and kept in the partner's own currency. Only Aromatics master products have them;
 * a partner with no row pays the product price less the hub partner discount.
 *
 * @property int $id
 * @property int $group_id
 * @property int $master_asset_id
 * @property int $org_partner_id the buyer → seller org_partners row (organisation_id buys from partner_id)
 * @property int $currency_id
 * @property string $price
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read MasterAsset $masterAsset
 * @property-read OrgPartner $orgPartner
 * @property-read Currency $currency
 */
class MasterAssetPartnerPrice extends Model implements Auditable
{
    use HasHistory;

    protected $guarded = [];

    protected array $auditInclude = [
        'price',
        'currency_id',
    ];

    public function generateTags(): array
    {
        return [
            'catalogue',
        ];
    }

    public function masterAsset(): BelongsTo
    {
        return $this->belongsTo(MasterAsset::class);
    }

    public function orgPartner(): BelongsTo
    {
        return $this->belongsTo(OrgPartner::class);
    }

    public function currency(): BelongsTo
    {
        return $this->belongsTo(Currency::class);
    }
}
