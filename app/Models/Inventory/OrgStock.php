<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 23 Jan 2024 08:52:47 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Models\Inventory;

use App\Enums\Catalogue\HealthRankEnum;
use App\Enums\Inventory\OrgStock\OrgStockQuantityStatusEnum;
use App\Enums\Inventory\OrgStock\OrgStockStateEnum;
use App\Models\Catalogue\Product;
use App\Models\Dispatching\BatchCode;
use App\Models\Dispatching\DeliveryNoteItem;
use App\Models\Goods\Stock;
use App\Models\Goods\TradeUnit;
use App\Models\Procurement\OrgSupplierProduct;
use App\Models\SysAdmin\Organisation;
use App\Models\Production\ArtefactComplianceItem;
use App\Models\Production\ArtefactLabel;
use App\Models\Traits\HasAttachments;
use App\Models\Traits\HasHistory;
use App\Models\Traits\InOrganisation;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use App\Models\Traits\HasSearch;
use OwenIt\Auditing\Contracts\Auditable;
use Spatie\MediaLibrary\HasMedia;
use Spatie\Sluggable\HasSlug;
use Spatie\Sluggable\SlugOptions;

/**
 * App\Models\Inventory\OrgStock
 *
 * @property int $id
 * @property int $group_id
 * @property int $organisation_id
 * @property int|null $stock_id
 * @property int|null $org_stock_family_id
 * @property int|null $customer_id
 * @property string $slug
 * @property string $code
 * @property string|null $name
 * @property numeric $sku_commercial_value
 * @property bool $is_sellable_in_organisation
 * @property bool $is_raw_material_in_organisation
 * @property bool $is_shipment_packaging
 * @property OrgStockStateEnum $state
 * @property OrgStockQuantityStatusEnum|null $quantity_status
 * @property numeric|null $quantity_in_locations stock quantity in units
 * @property numeric $value_in_locations
 * @property float|null $available_forecast days
 * @property array<array-key, mixed> $data
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $activated_in_organisation_at
 * @property Carbon|null $discontinuing_in_organisation_at
 * @property Carbon|null $discontinued_in_organisation_at
 * @property Carbon|null $fetched_at
 * @property Carbon|null $last_fetched_at
 * @property Carbon|null $deleted_at
 * @property string|null $source_id
 * @property int|null $picking_location_id
 * @property int|null $picking_dropshipping_location_id
 * @property int|null $packed_in Number of trade units usually packed together
 * @property bool $is_single_trade_unit Indicates if the org stock has a single trade unit
 * @property numeric $quantity_in_submitted_orders
 * @property numeric $quantity_to_be_picked
 * @property numeric $quantity_reserved_for_pre_orders Goods that arrived for pre-orders whose balance is due
 * @property numeric $quantity_available
 * @property numeric $source_quantity_in_submitted_orders
 * @property numeric $source_quantity_to_be_picked
 * @property bool $is_on_demand
 * @property bool $is_fresh
 * @property bool $is_made_in_house
 * @property bool $is_excluded_from_auto_ordering
 * @property bool $has_been_in_warehouse
 * @property HealthRankEnum|null $health_rank
 * @property bool $movements_fixed
 * @property numeric|null $sku_value
 * @property numeric|null $lpp_per_sku
 * @property numeric|null $minimum_stock
 * @property Carbon|null $minimum_stock_alerted_at
 * @property numeric|null $current_supplier_sku_cost
 * @property int $current_batch_codes
 * @property int|null $main_batch_code_id
 * @property string|null $note_to_pickers
 * @property string|null $note_to_packers
 * @property array<array-key, mixed>|null $consumables [{"code": "IAL01", "quantity": 1}] the packer adds per product ordered
 * @property string|null $barcode the outer/SKO CODE 128 barcode printed on the external packing
 * @property string|null $unit_barcode the EAN13 of the individual unit
 * @property string|null $tariff_code this organisation's own code, from its Aurora; its heading may differ from the trade unit's
 * @property bool $independent_barcode
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Helpers\Audit> $audits
 * @property-read \Illuminate\Database\Eloquent\Collection<int, BatchCode> $batchCodes
 * @property-read \Illuminate\Database\Eloquent\Collection<int, DeliveryNoteItem> $deliveryNoteItems
 * @property-read \App\Models\SysAdmin\Group|null $group
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Inventory\LocationOrgStock> $locationOrgStocks
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Inventory\Location> $locations
 * @property-read BatchCode|null $mainBatchCode
 * @property-read \App\Models\Inventory\OrgStockFamily|null $orgStockFamily
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Inventory\OrgStockMovement> $orgStockMovements
 * @property-read \Illuminate\Database\Eloquent\Collection<int, OrgSupplierProduct> $orgSupplierProducts
 * @property-read Organisation $organisation
 * @property-read \Illuminate\Database\Eloquent\Collection<int, Product> $products
 * @property-read \App\Models\Inventory\OrgStockStats|null $stats
 * @property-read Stock|null $stock
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Inventory\OrgStockTimeSeries> $timeSeries
 * @property-read \Illuminate\Database\Eloquent\Collection<int, TradeUnit> $tradeUnits
 * @method static \Database\Factories\Inventory\OrgStockFactory factory($count = null, $state = [])
 * @method static Builder<static>|OrgStock newModelQuery()
 * @method static Builder<static>|OrgStock newQuery()
 * @method static Builder<static>|OrgStock onlyTrashed()
 * @method static Builder<static>|OrgStock query()
 * @method static Builder<static>|OrgStock withTrashed(bool $withTrashed = true)
 * @method static Builder<static>|OrgStock withoutTrashed()
 * @property-read \Illuminate\Database\Eloquent\Collection<int, ArtefactLabel> $labels
 * @property-read \Illuminate\Database\Eloquent\Collection<int, ArtefactComplianceItem> $complianceItems
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Helpers\Media> $attachments
 * @mixin \Eloquent
 */
class OrgStock extends Model implements Auditable, HasMedia
{
    use HasAttachments;
    use HasFactory;
    use HasHistory;
    use HasSlug;
    use InOrganisation;
    use SoftDeletes;
    use HasSearch;

    protected $casts = [
        'data'                             => 'array',
        'consumables'                      => 'array',
        'activated_in_organisation_at'     => 'datetime',
        'discontinuing_in_organisation_at' => 'datetime',
        'discontinued_in_organisation_at'  => 'datetime',
        'state'                            => OrgStockStateEnum::class,
        'health_rank'                      => HealthRankEnum::class,
        'quantity_status'                  => OrgStockQuantityStatusEnum::class,
        'fetched_at'                       => 'datetime',
        'last_fetched_at'                  => 'datetime',
        'quantity_in_locations'            => 'decimal:3',
        'quantity_available'               => 'decimal:3',
        'minimum_stock'                    => 'decimal:3',
        'minimum_stock_alerted_at'         => 'datetime',
        'is_shipment_packaging'            => 'boolean',
    ];

    protected $attributes = [
        'data' => '{}',
    ];

    protected $guarded = [];

    public function searchIndexShouldBeUpdated(): bool
    {
        return $this->wasRecentlyCreated
            || $this->wasChanged([
                'code',
                'state',
                'name',
                'organisation_id',
                'created_at'
            ]);
    }

    public function toSearchableArray(): array
    {
        return [
            'id'              => (string)$this->id,
            'organisation_id' => $this->organisation_id,
            'code'            => $this->code,
            'name'            => $this->name,
            'supplier_codes'  => $this->searchableSupplierCodes(),
            'state'           => $this->state->value,
            'created_at'      => is_string($this->created_at) ? Carbon::parse($this->created_at)->timestamp : $this->created_at->timestamp,
        ];
    }

    /**
     * Some suppliers only print their own code on the box, so staff search for the SKO by it.
     */
    public function searchableSupplierCodes(): string
    {
        return DB::table('org_stock_has_org_supplier_products')
            ->join('org_supplier_products', 'org_supplier_products.id', 'org_stock_has_org_supplier_products.org_supplier_product_id')
            ->join('supplier_products', 'supplier_products.id', 'org_supplier_products.supplier_product_id')
            ->where('org_stock_has_org_supplier_products.org_stock_id', $this->id)
            ->orderBy('supplier_products.code')
            ->distinct()
            ->pluck('supplier_products.code')
            ->implode(' ');
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function generateTags(): array
    {
        return [
            'goods',
        ];
    }

    protected array $auditInclude = [
        'code',
        'name',
        'state',
        'is_on_demand',
        'is_fresh',
        'is_made_in_house',
        'is_excluded_from_auto_ordering',
        'minimum_stock',
        'packed_in',
        'barcode',
    ];

    public function packedIn(): int
    {
        return max(1, (int) $this->packed_in);
    }

    public function getSlugOptions(): SlugOptions
    {
        return SlugOptions::create()
            ->generateSlugsFrom(function () {
                return $this->code.' '.$this->organisation->code;
            })
            ->doNotGenerateSlugsOnUpdate()
            ->saveSlugsTo('slug');
    }

    public function stock(): BelongsTo
    {
        return $this->belongsTo(Stock::class);
    }

    public function orgStockFamily(): BelongsTo
    {
        return $this->belongsTo(OrgStockFamily::class);
    }

    /**
     * GB-origin SKOs travel on their own pallet to partners that split them off, unless the
     * organisation switched their family off.
     */
    public function isOnGbPallet(): bool
    {
        return $this->stock->is_gb_origin && ($this->orgStockFamily?->gb_separate_pallet ?? true);
    }

    public static function gbPalletSql(string $orgStockAlias): string
    {
        return "(coalesce((select gb_stock.is_gb_origin from stocks gb_stock where gb_stock.id = {$orgStockAlias}.stock_id), false)
            and coalesce((select gb_family.gb_separate_pallet from org_stock_families gb_family where gb_family.id = {$orgStockAlias}.org_stock_family_id), true))";
    }

    /**
     * SQL for the units in one SKO of a supplier product as the organisation holds it: the packed_in of its org stock
     * linked to that supplier product, else the supplier's own pack, else 1.
     */
    public static function unitsPerSkoSql(string $organisationId, string $supplierProductId): string
    {
        $activeState = OrgStockStateEnum::ACTIVE->value;

        return "coalesce(
            (select nullif(sko.packed_in, 0) from org_stocks sko
                inner join stock_has_supplier_products sko_link on sko_link.stock_id = sko.stock_id
                where sko_link.supplier_product_id = {$supplierProductId}
                    and sko.organisation_id = {$organisationId}
                order by (sko.state = '{$activeState}') desc, sko.id
                limit 1),
            (select sko_sp.units_per_pack from supplier_products sko_sp where sko_sp.id = {$supplierProductId}),
            1)";
    }

    public function locationOrgStocks(): HasMany
    {
        return $this->hasMany(LocationOrgStock::class);
    }

    public function orgStockMovements(): HasMany
    {
        return $this->hasMany(OrgStockMovement::class);
    }

    public function stats(): HasOne
    {
        return $this->hasOne(OrgStockStats::class);
    }

    public function orgSupplierProducts(): BelongsToMany
    {
        return $this->belongsToMany(OrgSupplierProduct::class, 'org_stock_has_org_supplier_products')
            ->withPivot(['status', 'local_priority'])->withTimestamps()
            ->orderByPivot('local_priority', 'desc');
    }

    public function tradeUnits(): MorphToMany
    {
        return $this->morphToMany(TradeUnit::class, 'model', 'model_has_trade_units')->withPivot(['quantity', 'notes'])->withTimestamps();
    }

    public function timeSeries(): HasMany
    {
        return $this->hasMany(OrgStockTimeSeries::class);
    }

    public function products(): BelongsToMany
    {
        return $this->belongsToMany(
            Product::class,
            'product_has_org_stocks',
        )->withPivot(['quantity', 'notes'])->withTimestamps();
    }

    public function locations(): BelongsToMany
    {
        return $this->belongsToMany(Location::class, 'location_org_stocks')
            ->withPivot(['type', 'picking_priority', 'value', 'dropshipping_pipe', 'quantity', 'notes']);
    }

    /**
     * Labels belong to the master SKO, so every organisation stocking it prints the same ones.
     */
    public function labels(): HasMany
    {
        return $this->hasMany(ArtefactLabel::class, 'stock_id', 'stock_id')->orderBy('name');
    }

    public function complianceItems(): HasMany
    {
        return $this->hasMany(ArtefactComplianceItem::class, 'stock_id', 'stock_id');
    }

    public function batchCodes(): HasMany
    {
        return $this->hasMany(BatchCode::class);
    }

    public function mainBatchCode(): BelongsTo
    {
        return $this->belongsTo(BatchCode::class, 'main_batch_code_id');
    }

    public function deliveryNoteItems(): HasMany
    {
        return $this->hasMany(DeliveryNoteItem::class);
    }

    public function getTariffCode(): ?string
    {
        return $this->tariff_code ?: $this->tradeUnits->first()?->getTariffCodeForOrganisation($this->organisation_id);
    }

    /**
     * The organisation may use other national digits than the trade unit, never another 6 digit heading.
     */
    public function hasTariffCodeHeadingMismatch(): bool
    {
        $tradeUnitHeading = $this->tradeUnits->first()?->getTariffCodeHeading();
        $ownCode          = preg_replace('/\D/', '', (string) $this->tariff_code);

        return $tradeUnitHeading && strlen($ownCode) >= 6 && substr($ownCode, 0, 6) !== $tradeUnitHeading;
    }
}
