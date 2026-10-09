<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 03 May 2024 14:39:32 British Summer Time, Sheffield, UK
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Models\Procurement;

use App\Actions\Helpers\CurrencyExchange\GetCurrencyExchange;
use App\Models\GoodsIn\StockDelivery;
use App\Models\SysAdmin\Organisation;
use App\Models\Traits\InOrganisation;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use RuntimeException;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/**
 * @property int $id
 * @property int $group_id
 * @property int $organisation_id
 * @property int $partner_id
 * @property bool $status
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property array<array-key, mixed> $sources
 * @property-read \App\Models\SysAdmin\Group|null $group
 * @property-read Organisation $organisation
 * @property-read Organisation $partner
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Procurement\PurchaseOrder> $purchaseOrders
 * @property-read \App\Models\Procurement\OrgPartnerStats|null $stats
 * @property-read \Illuminate\Database\Eloquent\Collection<int, StockDelivery> $stockDeliveries
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OrgPartner newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OrgPartner newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OrgPartner query()
 * @mixin \Eloquent
 */
class OrgPartner extends Model
{
    use InOrganisation;

    protected $casts = [
        'sources'           => 'array',
        'data'              => 'array',
        'split_cosmetics'   => 'boolean',
        'next_shipment_on'  => 'date',
    ];

    protected $attributes = [
        'sources' => '{}',
        'data'    => '{}',
    ];


    protected $guarded = [];



    public function stats(): HasOne
    {
        return $this->hasOne(OrgPartnerStats::class);
    }

    public function purchaseOrders(): MorphMany
    {
        return $this->morphMany(PurchaseOrder::class, 'parent');
    }

    public function stockDeliveries(): MorphMany
    {
        return $this->morphMany(StockDelivery::class, 'parent');
    }

    public function goodsOutLocation(): BelongsTo
    {
        return $this->belongsTo(\App\Models\Inventory\Location::class, 'goods_out_location_id');
    }

    public function cosmeticGoodsOutLocation(): BelongsTo
    {
        return $this->belongsTo(\App\Models\Inventory\Location::class, 'cosmetic_goods_out_location_id');
    }

    /**
     * The bay a SKO of this partner is gathered in: the cosmetic bay for cosmetic SKOs when the
     * partner's cosmetics are split off and that bay is set, the partner's goods out bay otherwise.
     */
    public function bayIdFor(bool $isCosmetic): ?int
    {
        if ($isCosmetic && $this->split_cosmetics && $this->cosmetic_goods_out_location_id) {
            return $this->cosmetic_goods_out_location_id;
        }

        return $this->goods_out_location_id;
    }

    public function bayFor(bool $isCosmetic): ?\App\Models\Inventory\Location
    {
        $bayId = $this->bayIdFor($isCosmetic);

        return $bayId && $bayId === $this->cosmetic_goods_out_location_id ? $this->cosmeticGoodsOutLocation : $this->goodsOutLocation;
    }

    /**
     * @return array<int, int>
     */
    public function bayIds(): array
    {
        return array_values(array_filter([$this->goods_out_location_id, $this->cosmetic_goods_out_location_id]));
    }

    public function scopeWithBay(Builder $query, int $locationId): Builder
    {
        return $query->where(fn (Builder $inner) => $inner->where('goods_out_location_id', $locationId)->orWhere('cosmetic_goods_out_location_id', $locationId));
    }

    public static function bayIdSql(string $orgPartnerAlias, string $isCosmeticExpression): string
    {
        return "(case when {$orgPartnerAlias}.split_cosmetics and {$isCosmeticExpression} and {$orgPartnerAlias}.cosmetic_goods_out_location_id is not null
            then {$orgPartnerAlias}.cosmetic_goods_out_location_id else {$orgPartnerAlias}.goods_out_location_id end)";
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(\App\Models\CRM\Customer::class);
    }

    public function partner(): BelongsTo
    {
        return $this->belongsTo(Organisation::class, 'partner_id');
    }

    public function shoppingListItems(): HasMany
    {
        return $this->hasMany(PartnerShoppingListItem::class);
    }

    /**
     * Every money figure shown for partner shopping is in the buying organisation's currency;
     * partner-side amounts (seller shop prices, stock delivery costs) are stored in the partner's.
     */
    public function exchangeToOrgCurrency(): float
    {
        return GetCurrencyExchange::run($this->partner->currency, $this->organisation->currency)
            ?? throw new RuntimeException("No exchange rate from {$this->partner->currency->code} to {$this->organisation->currency->code}");
    }

}
