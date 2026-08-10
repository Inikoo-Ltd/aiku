<?php

/*
 * Author: Artha <artha@aw-advantage.com>
 * Created: Mon, 17 Apr 2023 11:52:15 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Models\GoodsIn;

use App\Enums\GoodsIn\StockDelivery\StockDeliveryStateEnum;
use App\Models\Helpers\Address;
use App\Models\Helpers\Currency;
use App\Models\Procurement\PurchaseOrder;
use App\Models\SysAdmin\Organisation;
use App\Models\Traits\HasAddress;
use App\Models\Traits\HasAddresses;
use App\Models\Traits\HasAttachments;
use App\Models\Traits\HasHistory;
use App\Models\Traits\InOrganisation;
use App\Models\Traits\HasSearch;
use Eloquent;
use Illuminate\Support\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use OwenIt\Auditing\Contracts\Auditable;
use Spatie\MediaLibrary\HasMedia;
use Spatie\Sluggable\HasSlug;
use Spatie\Sluggable\SlugOptions;

/**
 * @property int $id
 * @property int $group_id
 * @property int $organisation_id
 * @property string $slug
 * @property string $parent_type OrgAgent|OrgSupplier|Partner(intra-group sales)
 * @property int $parent_id
 * @property string $parent_code Parent code on the time of consolidation
 * @property string $parent_name Parent name on the time of consolidation
 * @property string $reference
 * @property StockDeliveryStateEnum $state
 * @property \Illuminate\Support\Carbon $date latest relevant date
 * @property \Illuminate\Support\Carbon|null $dispatched_at
 * @property \Illuminate\Support\Carbon|null $received_at
 * @property \Illuminate\Support\Carbon|null $checked_at
 * @property \Illuminate\Support\Carbon|null $placed_at
 * @property \Illuminate\Support\Carbon|null $cancelled_at
 * @property \Illuminate\Support\Carbon|null $not_received_at
 * @property int|null $agent_id
 * @property int|null $supplier_id
 * @property int|null $partner_id
 * @property int $number_purchase_orders
 * @property float|null $gross_weight
 * @property float|null $net_weight
 * @property int $number_stock_delivery_items Number supplier deliveries
 * @property int $number_stock_delivery_items_except_cancelled Number supplier deliveries
 * @property int $number_stock_delivery_items_state_in_process
 * @property int $number_stock_delivery_items_state_confirmed
 * @property int $number_stock_delivery_items_state_ready_to_ship
 * @property int $number_stock_delivery_items_state_dispatched
 * @property int $number_stock_delivery_items_state_received
 * @property int $number_stock_delivery_items_state_checked
 * @property int $number_stock_delivery_items_state_placed
 * @property int $number_stock_delivery_items_state_cancelled
 * @property int $number_stock_delivery_items_state_not_received
 * @property int $currency_id
 * @property numeric|null $grp_exchange
 * @property numeric|null $org_exchange
 * @property bool $is_costed
 * @property array<array-key, mixed> $cost_data
 * @property numeric|null $cost_items
 * @property numeric|null $cost_extra
 * @property numeric|null $cost_shipping
 * @property numeric|null $cost_duties
 * @property numeric $cost_tax
 * @property numeric $cost_total
 * @property array<array-key, mixed> $data
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property \Illuminate\Support\Carbon|null $fetched_at
 * @property \Illuminate\Support\Carbon|null $last_fetched_at
 * @property \Illuminate\Support\Carbon|null $deleted_at
 * @property string|null $source_id
 * @property int $number_stock_delivery_items_under_delivered unit_quantity_checked < unit_quantity
 * @property int $number_stock_delivery_items_over_delivered unit_quantity_checked > unit_quantity
 * @property \Illuminate\Support\Carbon|null $confirmed_at
 * @property \Illuminate\Support\Carbon|null $ready_to_ship_at
 * @property \Illuminate\Support\Carbon|null $booking_in_at
 * @property \Illuminate\Support\Carbon|null $booked_in_at
 * @property numeric|null $cbm carton cubic meters
 * @property-read Address|null $address
 * @property-read Collection<int, Address> $addresses
 * @property-read \Spatie\MediaLibrary\MediaCollections\Models\Collections\MediaCollection<int, \App\Models\Helpers\Media> $attachments
 * @property-read Collection<int, \App\Models\Helpers\Audit> $audits
 * @property-read Currency $currency
 * @property-read \App\Models\SysAdmin\Group|null $group
 * @property-read Collection<int, \App\Models\GoodsIn\StockDeliveryItem> $items
 * @property-read \Spatie\MediaLibrary\MediaCollections\Models\Collections\MediaCollection<int, \App\Models\Helpers\Media> $media
 * @property-read Organisation $organisation
 * @property-read Model|\Eloquent $parent
 * @property-read Collection<int, PurchaseOrder> $purchaseOrders
 * @method static \Database\Factories\GoodsIn\StockDeliveryFactory factory($count = null, $state = [])
 * @method static Builder<static>|StockDelivery newModelQuery()
 * @method static Builder<static>|StockDelivery newQuery()
 * @method static Builder<static>|StockDelivery onlyTrashed()
 * @method static Builder<static>|StockDelivery query()
 * @method static Builder<static>|StockDelivery withTrashed(bool $withTrashed = true)
 * @method static Builder<static>|StockDelivery withoutTrashed()
 * @mixin Eloquent
 */
class StockDelivery extends Model implements HasMedia, Auditable
{
    use SoftDeletes;
    use HasAddress;
    use HasAddresses;
    use HasSlug;
    use HasFactory;
    use InOrganisation;
    use HasAttachments;
    use HasHistory;
    use HasSearch;


    protected $casts = [
        'data'            => 'array',
        'cost_data'       => 'array',
        'state'           => StockDeliveryStateEnum::class,
        'date'            => 'datetime',
        'confirmed_at'    => 'datetime',
        'ready_to_ship_at' => 'datetime',
        'dispatched_at'   => 'datetime',
        'received_at'     => 'datetime',
        'checked_at'      => 'datetime',
        'booking_in_at'   => 'datetime',
        'booked_in_at'    => 'datetime',
        'placed_at'       => 'datetime',
        'cancelled_at'    => 'datetime',
        'not_received_at' => 'datetime',
        'fetched_at'      => 'datetime',
        'last_fetched_at' => 'datetime',
    ];

    protected $attributes = [
        'data'      => '{}',
        'cost_data' => '{}',
    ];

    protected $guarded = [];

    public function getSlugOptions(): SlugOptions
    {
        return SlugOptions::create()
            ->generateSlugsFrom('reference')
            ->saveSlugsTo('slug')
            ->doNotGenerateSlugsOnUpdate();
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function generateTags(): array
    {
        return [
            'procurement'
        ];
    }

    protected array $auditInclude = [
        'reference',
    ];

    public function searchIndexShouldBeUpdated(): bool
    {
        return $this->wasRecentlyCreated || $this->wasChanged([
                'organisation_id',
                'state',
                'reference',
                'parent_code',
                'parent_name',
            ]);
    }

    public function toSearchableArray(): array
    {
        return [
            'id'              => (string)$this->id,
            'organisation_id' => $this->organisation_id,
            'state'           => $this->state?->value,
            'reference'       => (string)$this->reference,
            'slug'            => $this->slug,
            'parent_code'     => (string)$this->parent_code,
            'parent_name'     => (string)$this->parent_name,
            'created_at'      => is_string($this->created_at) ? Carbon::parse($this->created_at)->timestamp : $this->created_at->timestamp,
        ];
    }

    public function purchaseOrders(): BelongsToMany
    {
        return $this->belongsToMany(PurchaseOrder::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(StockDeliveryItem::class);
    }

    public function costs(): HasMany
    {
        return $this->hasMany(StockDeliveryCost::class);
    }

    public function parent(): MorphTo
    {
        return $this->morphTo();
    }

    public function currency(): BelongsTo
    {
        return $this->belongsTo(Currency::class);
    }
}
