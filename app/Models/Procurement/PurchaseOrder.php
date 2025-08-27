<?php

/*
 * Author: Artha <artha@aw-advantage.com>
 * Created: Mon, 17 Apr 2023 11:42:36 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Models\Procurement;

use App\Enums\Procurement\PurchaseOrder\PurchaseOrderStateEnum;
use App\Enums\Procurement\PurchaseOrder\PurchaseOrderDeliveryStateEnum;
use App\Models\Helpers\Address;
use App\Models\Helpers\Currency;
use App\Models\SysAdmin\Organisation;
use App\Models\Traits\HasAddress;
use App\Models\Traits\HasAddresses;
use App\Models\Traits\HasAttachments;
use App\Models\Traits\HasHistory;
use App\Models\Traits\HasUniversalSearch;
use App\Models\Traits\InOrganisation;
use Eloquent;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use OwenIt\Auditing\Contracts\Auditable;
use Spatie\MediaLibrary\HasMedia;
use Spatie\Sluggable\HasSlug;
use Spatie\Sluggable\SlugOptions;

/**
 * App\Models\Procurement\PurchaseOrder
 *
 * @property int $id
 * @property int $group_id
 * @property int $organisation_id
 * @property string $slug
 * @property string $parent_type OrgAgent|OrgSupplier|Partner(intra-group sales)
 * @property int $parent_id
 * @property string $parent_code Parent code on the time of consolidation
 * @property string $parent_name Parent name on the time of consolidation
 * @property string $reference
 * @property PurchaseOrderStateEnum $state
 * @property PurchaseOrderDeliveryStateEnum $delivery_state
 * @property string|null $notes
 * @property \Illuminate\Support\Carbon $date latest relevant date
 * @property string|null $in_process_at
 * @property \Illuminate\Support\Carbon|null $submitted_at
 * @property \Illuminate\Support\Carbon|null $confirmed_at
 * @property \Illuminate\Support\Carbon|null $settled_at
 * @property \Illuminate\Support\Carbon|null $cancelled_at
 * @property string|null $not_received_at
 * @property int|null $agent_id
 * @property int|null $supplier_id
 * @property int|null $partner_id
 * @property float|null $gross_weight
 * @property float|null $net_weight
 * @property int $number_purchase_order_transactions
 * @property int $number_current_purchase_order_transactions Number purchase order transactions (except: cancelled and not_received)
 * @property int $number_open_purchase_order_transactions Number purchase order transactions (except: in_process,settled,cancelled,not_received)
 * @property int $number_purchase_order_transactions_state_in_process
 * @property int $number_purchase_order_transactions_state_submitted
 * @property int $number_purchase_order_transactions_state_confirmed
 * @property int $number_purchase_order_transactions_state_settled
 * @property int $number_purchase_order_transactions_state_cancelled
 * @property int $number_purchase_order_transactions_state_not_received
 * @property int $number_purchase_orders_transactions_delivery_state_in_process
 * @property int $number_purchase_orders_transactions_delivery_state_confirmed
 * @property int $number_purchase_orders_transactions_delivery_state_ready_to_shi
 * @property int $number_purchase_orders_transactions_delivery_state_dispatched
 * @property int $number_purchase_orders_transactions_delivery_state_received
 * @property int $number_purchase_orders_transactions_delivery_state_checked
 * @property int $number_purchase_orders_transactions_delivery_state_settled
 * @property int $number_purchase_orders_transactions_delivery_state_not_received
 * @property int $number_purchase_orders_transactions_delivery_state_cancelled
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
 * @property-read Address|null $address
 * @property-read Collection<int, Address> $addresses
 * @property-read \Spatie\MediaLibrary\MediaCollections\Models\Collections\MediaCollection<int, \App\Models\Helpers\Media> $attachments
 * @property-read Collection<int, \App\Models\Helpers\Audit> $audits
 * @property-read Currency $currency
 * @property-read \App\Models\SysAdmin\Group $group
 * @property-read \Spatie\MediaLibrary\MediaCollections\Models\Collections\MediaCollection<int, \App\Models\Helpers\Media> $media
 * @property-read Organisation $organisation
 * @property-read Model|\Eloquent $parent
 * @property-read Collection<int, \App\Models\Procurement\PurchaseOrderTransaction> $purchaseOrderTransactions
 * @property-read \App\Models\Helpers\UniversalSearch|null $universalSearch
 * @method static \Database\Factories\Procurement\PurchaseOrderFactory factory($count = null, $state = [])
 * @method static Builder<static>|PurchaseOrder newModelQuery()
 * @method static Builder<static>|PurchaseOrder newQuery()
 * @method static Builder<static>|PurchaseOrder onlyTrashed()
 * @method static Builder<static>|PurchaseOrder query()
 * @method static Builder<static>|PurchaseOrder withTrashed(bool $withTrashed = true)
 * @method static Builder<static>|PurchaseOrder withoutTrashed()
 * @mixin Eloquent
 */
class PurchaseOrder extends Model implements Auditable, HasMedia
{
    use SoftDeletes;
    use HasAddress;
    use HasAddresses;
    use HasSlug;
    use HasFactory;
    use HasHistory;
    use InOrganisation;
    use HasAttachments;
    use HasUniversalSearch;


    protected $casts = [
        'data'            => 'array',
        'cost_data'       => 'array',
        'state'           => PurchaseOrderStateEnum::class,
        'delivery_state' => PurchaseOrderDeliveryStateEnum::class,
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

    public function parent(): MorphTo
    {
        return $this->morphTo();
    }

    public function purchaseOrderTransactions(): HasMany
    {
        return $this->hasMany(PurchaseOrderTransaction::class);
    }

    public function currency(): BelongsTo
    {
        return $this->belongsTo(Currency::class);
    }
}
