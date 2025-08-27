<?php

/*
 * Author: Artha <artha@aw-advantage.com>
 * Created: Mon, 17 Apr 2023 11:52:15 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Models\Procurement;

use App\Enums\Procurement\StockDelivery\StockDeliveryStateEnum;
use App\Models\Helpers\Address;
use App\Models\SysAdmin\Organisation;
use App\Models\Traits\HasAddress;
use App\Models\Traits\HasAddresses;
use App\Models\Traits\HasAttachments;
use App\Models\Traits\HasHistory;
use App\Models\Traits\InOrganisation;
use Eloquent;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
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
 * @property string|null $grp_exchange
 * @property string|null $org_exchange
 * @property bool $is_costed
 * @property array<array-key, mixed> $cost_data
 * @property string|null $cost_items
 * @property string|null $cost_extra
 * @property string|null $cost_shipping
 * @property string|null $cost_duties
 * @property string $cost_tax
 * @property string $cost_total
 * @property array<array-key, mixed> $data
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property \Illuminate\Support\Carbon|null $fetched_at
 * @property \Illuminate\Support\Carbon|null $last_fetched_at
 * @property \Illuminate\Support\Carbon|null $deleted_at
 * @property string|null $source_id
 * @property-read Address|null $address
 * @property-read Collection<int, Address> $addresses
 * @property-read \Spatie\MediaLibrary\MediaCollections\Models\Collections\MediaCollection<int, \App\Models\Helpers\Media> $attachments
 * @property-read Collection<int, \App\Models\Helpers\Audit> $audits
 * @property-read \App\Models\SysAdmin\Group $group
 * @property-read Collection<int, \App\Models\Procurement\StockDeliveryItem> $items
 * @property-read \Spatie\MediaLibrary\MediaCollections\Models\Collections\MediaCollection<int, \App\Models\Helpers\Media> $media
 * @property-read Organisation $organisation
 * @property-read Model|\Eloquent $parent
 * @property-read Collection<int, \App\Models\Procurement\PurchaseOrder> $purchaseOrders
 * @method static \Database\Factories\Procurement\StockDeliveryFactory factory($count = null, $state = [])
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


    protected $casts = [
        'data'            => 'array',
        'cost_data'       => 'array',
        'state'           => StockDeliveryStateEnum::class,
        'date'            => 'datetime',
        'dispatched_at'   => 'datetime',
        'received_at'     => 'datetime',
        'checked_at'      => 'datetime',
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


    public function purchaseOrders(): BelongsToMany
    {
        return $this->belongsToMany(PurchaseOrder::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(StockDeliveryItem::class);
    }

    public function parent(): MorphTo
    {
        return $this->morphTo();
    }
}
