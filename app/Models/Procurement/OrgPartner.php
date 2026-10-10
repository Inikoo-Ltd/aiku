<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 03 May 2024 14:39:32 British Summer Time, Sheffield, UK
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Models\Procurement;

use App\Actions\Helpers\CurrencyExchange\GetCurrencyExchange;
use App\Models\GoodsIn\StockDelivery;
use App\Models\Inventory\OrgStock;
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
        'split_gb_origin'   => 'boolean',
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

    public function gbGoodsOutLocation(): BelongsTo
    {
        return $this->belongsTo(\App\Models\Inventory\Location::class, 'gb_goods_out_location_id');
    }

    /**
     * Which separate pallet a SKO of this partner travels on: cosmetics when the partner's cosmetics
     * are split off, GB-origin goods when those are, null for the partner's normal pallet. A split
     * only counts once its bay is set.
     */
    public function splitFor(bool $isCosmetic, bool $isGbPallet): ?string
    {
        return match (true) {
            $isCosmetic && $this->split_cosmetics && $this->cosmetic_goods_out_location_id !== null => 'cosmetic',
            $isGbPallet && $this->split_gb_origin && $this->gb_goods_out_location_id !== null      => 'gb',
            default                                                                                 => null,
        };
    }

    public function bayIdFor(bool $isCosmetic, bool $isGbPallet = false): ?int
    {
        return match ($this->splitFor($isCosmetic, $isGbPallet)) {
            'cosmetic' => $this->cosmetic_goods_out_location_id,
            'gb'       => $this->gb_goods_out_location_id,
            default    => $this->goods_out_location_id,
        };
    }

    public function bayFor(bool $isCosmetic, bool $isGbPallet = false): ?\App\Models\Inventory\Location
    {
        return match ($this->splitFor($isCosmetic, $isGbPallet)) {
            'cosmetic' => $this->cosmeticGoodsOutLocation,
            'gb'       => $this->gbGoodsOutLocation,
            default    => $this->goodsOutLocation,
        };
    }

    public function isGbPallet(int $stockId): bool
    {
        return (bool) OrgStock::where('organisation_id', $this->organisation_id)->where('stock_id', $stockId)->first()?->isOnGbPallet();
    }

    /**
     * @return array<int, int>
     */
    public function bayIds(): array
    {
        return array_values(array_filter([$this->goods_out_location_id, $this->cosmetic_goods_out_location_id, $this->gb_goods_out_location_id]));
    }

    public function scopeWithBay(Builder $query, int $locationId): Builder
    {
        return $query->where(fn (Builder $inner) => $inner->where('goods_out_location_id', $locationId)
            ->orWhere('cosmetic_goods_out_location_id', $locationId)
            ->orWhere('gb_goods_out_location_id', $locationId));
    }

    /** SQL twin of splitFor(), for the stock whose id is $stockIdExpression. */
    public static function splitSql(string $orgPartnerAlias, string $stockIdExpression): string
    {
        return "(case
            when {$orgPartnerAlias}.split_cosmetics and {$orgPartnerAlias}.cosmetic_goods_out_location_id is not null
                and coalesce((select split_stock.is_cosmetic from stocks split_stock where split_stock.id = {$stockIdExpression}), false) then 'cosmetic'
            when {$orgPartnerAlias}.split_gb_origin and {$orgPartnerAlias}.gb_goods_out_location_id is not null
                and exists (select 1 from org_stocks split_org_stock where split_org_stock.organisation_id = {$orgPartnerAlias}.organisation_id
                    and split_org_stock.stock_id = {$stockIdExpression} and ".OrgStock::gbPalletSql('split_org_stock').") then 'gb'
        end)";
    }

    public static function bayIdSql(string $orgPartnerAlias, string $stockIdExpression): string
    {
        return "(case ".self::splitSql($orgPartnerAlias, $stockIdExpression)."
            when 'cosmetic' then {$orgPartnerAlias}.cosmetic_goods_out_location_id
            when 'gb' then {$orgPartnerAlias}.gb_goods_out_location_id
            else {$orgPartnerAlias}.goods_out_location_id end)";
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
