<?php

/*
 * author Arya Permana - Kirin
 * created on 28-11-2024-10h-34m
 * github: https://github.com/KirinZero0
 * copyright 2024
*/

namespace App\Models\SupplyChain;

use App\Enums\SupplyChain\AgentSupplierPurchaseOrders\AgentSupplierPurchaseOrderDeliveryStateEnum;
use App\Enums\SupplyChain\AgentSupplierPurchaseOrders\AgentSupplierPurchaseOrderStateEnum;
use App\Models\Helpers\Currency;
use App\Models\Helpers\UniversalSearch;
use App\Models\Procurement\PurchaseOrder;
use App\Models\SysAdmin\Group;
use App\Models\Traits\HasAddress;
use App\Models\Traits\HasAddresses;
use App\Models\Traits\HasAttachments;
use App\Models\Traits\HasHistory;
use App\Models\Traits\HasUniversalSearch;
use App\Models\Traits\InGroup;
use Eloquent;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use OwenIt\Auditing\Contracts\Auditable;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Collections\MediaCollection;
use Spatie\Sluggable\HasSlug;
use Spatie\Sluggable\SlugOptions;

/**
 * @property int $id
 * @property int $group_id
 * @property string $reference
 * @property string $slug
 * @property int|null $purchase_order_id
 * @property int|null $supplier_id
 * @property AgentSupplierPurchaseOrderStateEnum $state
 * @property AgentSupplierPurchaseOrderDeliveryStateEnum $delivery_state
 * @property string|null $notes
 * @property \Illuminate\Support\Carbon $date latest relevant date
 * @property string|null $in_process_at
 * @property \Illuminate\Support\Carbon|null $submitted_at
 * @property \Illuminate\Support\Carbon|null $confirmed_at
 * @property \Illuminate\Support\Carbon|null $settled_at
 * @property \Illuminate\Support\Carbon|null $cancelled_at
 * @property string|null $not_received_at
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
 * @property int $number_stock_deliveries Number supplier deliveries
 * @property int $number_current_stock_deliveries Number supplier deliveries (except: cancelled and not_received)
 * @property int $number_is_costed_stock_deliveries is_costed=true
 * @property int $number_is_not_costed_stock_deliveries is_costed=false
 * @property int $number_is_costed_stock_deliveries_state_placed state=placed is_costed=true
 * @property int $number_is_not_costed_stock_deliveries_state_placed state=placed  is_costed=true
 * @property int $number_stock_deliveries_state_in_process
 * @property int $number_stock_deliveries_state_confirmed
 * @property int $number_stock_deliveries_state_ready_to_ship
 * @property int $number_stock_deliveries_state_dispatched
 * @property int $number_stock_deliveries_state_received
 * @property int $number_stock_deliveries_state_checked
 * @property int $number_stock_deliveries_state_placed
 * @property int $number_stock_deliveries_state_cancelled
 * @property int $number_stock_deliveries_state_not_received
 * @property array<array-key, mixed> $data
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property \Illuminate\Support\Carbon|null $fetched_at
 * @property \Illuminate\Support\Carbon|null $last_fetched_at
 * @property \Illuminate\Support\Carbon|null $deleted_at
 * @property string|null $source_id
 * @property-read \App\Models\Helpers\Address|null $address
 * @property-read Collection<int, \App\Models\Helpers\Address> $addresses
 * @property-read MediaCollection<int, \App\Models\Helpers\Media> $attachments
 * @property-read Collection<int, \App\Models\Helpers\Audit> $audits
 * @property-read Currency $currency
 * @property-read Group $group
 * @property-read MediaCollection<int, \App\Models\Helpers\Media> $media
 * @property-read PurchaseOrder|null $purchaseOrder
 * @property-read \App\Models\SupplyChain\Supplier|null $supplier
 * @property-read UniversalSearch|null $universalSearch
 * @method static Builder<static>|AgentSupplierPurchaseOrder newModelQuery()
 * @method static Builder<static>|AgentSupplierPurchaseOrder newQuery()
 * @method static Builder<static>|AgentSupplierPurchaseOrder onlyTrashed()
 * @method static Builder<static>|AgentSupplierPurchaseOrder query()
 * @method static Builder<static>|AgentSupplierPurchaseOrder withTrashed(bool $withTrashed = true)
 * @method static Builder<static>|AgentSupplierPurchaseOrder withoutTrashed()
 * @mixin Eloquent
 */
class AgentSupplierPurchaseOrder extends Model implements HasMedia, Auditable
{
    use SoftDeletes;
    use HasAddress;
    use HasAddresses;
    use HasSlug;
    use HasHistory;
    use InGroup;
    use HasAttachments;
    use HasUniversalSearch;

    protected $casts = [
        'data'            => 'array',
        'cost_data'       => 'array',
        'state'           => AgentSupplierPurchaseOrderStateEnum::class,
        'delivery_state' => AgentSupplierPurchaseOrderDeliveryStateEnum::class,
        'date'            => 'datetime',
        'submitted_at'    => 'datetime',
        'confirmed_at'    => 'datetime',
        'manufactured_at' => 'datetime',
        'dispatched_at'   => 'datetime',
        'received_at'     => 'datetime',
        'checked_at'      => 'datetime',
        'settled_at'      => 'datetime',
        'cancelled_at'    => 'datetime',
        'fetched_at'      => 'datetime',
        'last_fetched_at' => 'datetime',
    ];

    protected $attributes = [
        'data'     => '{}',
        'cost_data' => '{}',
        'sources' => '{}',
    ];

    protected $guarded = [];

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function getSlugOptions(): SlugOptions
    {
        return SlugOptions::create()
            ->generateSlugsFrom('reference')
            ->doNotGenerateSlugsOnUpdate()
            ->saveSlugsTo('slug');
    }

    public function generateTags(): array
    {
        return [
            'supply-chain'
        ];
    }

    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class);
    }

    public function currency(): BelongsTo
    {
        return $this->belongsTo(Currency::class);
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }
}
