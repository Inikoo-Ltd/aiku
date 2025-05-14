<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 23 Feb 2023 22:27:25 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Models\Dispatching;

use App\Enums\Dispatching\DeliveryNote\DeliveryNoteStateEnum;
use App\Enums\Dispatching\DeliveryNote\DeliveryNoteTypeEnum;
use App\Models\Catalogue\Shop;
use App\Models\CRM\Customer;
use App\Models\Helpers\Address;
use App\Models\Helpers\Feedback;
use App\Models\Helpers\UniversalSearch;
use App\Models\HumanResources\Employee;
use App\Models\Inventory\Warehouse;
use App\Models\Ordering\Order;
use App\Models\SysAdmin\Group;
use App\Models\SysAdmin\Organisation;
use App\Models\Traits\HasAddresses;
use App\Models\Traits\HasHistory;
use App\Models\Traits\HasUniversalSearch;
use App\Models\Traits\InCustomer;
use Eloquent;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use OwenIt\Auditing\Contracts\Auditable;
use Spatie\Sluggable\HasSlug;
use Spatie\Sluggable\SlugOptions;

/**
 * App\Models\Dispatching\DeliveryNote
 *
 * @property int $id
 * @property int $group_id
 * @property int $organisation_id
 * @property string $slug
 * @property int $warehouse_id
 * @property int $shop_id
 * @property int $customer_id
 * @property int|null $customer_client_id
 * @property string $reference
 * @property DeliveryNoteTypeEnum $type
 * @property DeliveryNoteStateEnum $state
 * @property bool|null $can_dispatch
 * @property bool|null $restocking
 * @property string|null $email
 * @property string|null $phone
 * @property bool $delivery_locked
 * @property int|null $address_id
 * @property int|null $delivery_country_id
 * @property int|null $weight actual weight, grams
 * @property int $number_stocks
 * @property int $number_picks
 * @property bool $has_out_of_stocks
 * @property string $picking_percentage
 * @property string $packing_percentage
 * @property int|null $picker_id Main picker
 * @property int|null $packer_id Main packer
 * @property \Illuminate\Support\Carbon $date
 * @property string|null $queued_at
 * @property string|null $handling_at
 * @property string|null $handling_blocked_at
 * @property \Illuminate\Support\Carbon|null $packed_at
 * @property string|null $finalised_at
 * @property \Illuminate\Support\Carbon|null $dispatched_at
 * @property \Illuminate\Support\Carbon|null $cancelled_at
 * @property string|null $start_picking
 * @property string|null $end_picking
 * @property string|null $start_packing
 * @property string|null $end_packing
 * @property string|null $picking_on_hold_time Time when picking was put on hold (seconds)
 * @property string|null $packing_on_hold_time Time when packing was put on hold (seconds)
 * @property array<array-key, mixed> $data
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property \Illuminate\Support\Carbon|null $fetched_at
 * @property \Illuminate\Support\Carbon|null $last_fetched_at
 * @property \Illuminate\Support\Carbon|null $deleted_at
 * @property string|null $source_id
 * @property bool $is_vip Indicate if delivery note is for a VIP customer
 * @property int|null $as_organisation_id Indicate if delivery note is for an organisation in this group
 * @property int|null $as_employee_id Indicate if delivery note is for an employee
 * @property int $estimated_weight grams
 * @property int $effective_weight Used for UI tables (e.g. sorting), effective_weight=estimated_weight if weight is null, grams
 * @property-read Address|null $address
 * @property-read Collection<int, Address> $addresses
 * @property-read Collection<int, \App\Models\Helpers\Audit> $audits
 * @property-read Customer $customer
 * @property-read Address|null $deliveryAddress
 * @property-read Collection<int, \App\Models\Dispatching\DeliveryNoteItem> $deliveryNoteItems
 * @property-read Collection<int, Feedback> $feedbacks
 * @property-read Collection<int, Address> $fixedAddresses
 * @property-read Group $group
 * @property-read Collection<int, Order> $orders
 * @property-read Organisation $organisation
 * @property-read Employee|null $packer
 * @property-read Employee|null $picker
 * @property-read Collection<int, \App\Models\Dispatching\Shipment> $shipments
 * @property-read Shop $shop
 * @property-read \App\Models\Dispatching\DeliveryNoteStats|null $stats
 * @property-read UniversalSearch|null $universalSearch
 * @property-read Warehouse $warehouse
 * @method static Builder<static>|DeliveryNote newModelQuery()
 * @method static Builder<static>|DeliveryNote newQuery()
 * @method static Builder<static>|DeliveryNote onlyTrashed()
 * @method static Builder<static>|DeliveryNote query()
 * @method static Builder<static>|DeliveryNote withTrashed()
 * @method static Builder<static>|DeliveryNote withoutTrashed()
 * @mixin Eloquent
 */
class DeliveryNote extends Model implements Auditable
{
    use SoftDeletes;
    use HasSlug;
    use HasUniversalSearch;
    use HasFactory;
    use InCustomer;
    use HasAddresses;
    use HasHistory;

    protected $casts = [
        'data'  => 'array',
        'state' => DeliveryNoteStateEnum::class,
        'type'  => DeliveryNoteTypeEnum::class,

        'date'               => 'datetime',
        'order_submitted_at' => 'datetime',
        'assigned_at'        => 'datetime',
        'picking_at'         => 'datetime',
        'picked_at'          => 'datetime',
        'packing_at'         => 'datetime',
        'packed_at'          => 'datetime',
        'dispatched_at'      => 'datetime',
        'cancelled_at'       => 'datetime',
        'fetched_at'         => 'datetime',
        'last_fetched_at'    => 'datetime',
    ];

    protected $attributes = [
        'data' => '{}',
    ];

    protected $guarded = [];

    public function generateTags(): array
    {
        return ['dispatching'];
    }

    protected array $auditInclude = [
        'reference',
        'type',
        'net_amount',
        'currency_id',
        'grp_exchange',
        'org_exchange',
        'total_amount',
    ];

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

    public function orders(): BelongsToMany
    {
        return $this->belongsToMany(Order::class, 'delivery_note_order')->withTimestamps();
    }

    public function stats(): HasOne
    {
        return $this->hasOne(DeliveryNoteStats::class);
    }

    public function deliveryNoteItems(): HasMany
    {
        return $this->hasMany(DeliveryNoteItem::class);
    }

    public function shipments(): BelongsToMany
    {
        return $this->belongsToMany(Shipment::class);
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function address(): BelongsTo
    {
        return $this->belongsTo(Address::class);
    }

    public function deliveryAddress(): BelongsTo
    {
        return $this->belongsTo(Address::class, 'address_id');
    }

    public function picker(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'picker_id');
    }

    public function packer(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'packer_id');
    }

    public function fixedAddresses(): MorphToMany
    {
        return $this->morphToMany(Address::class, 'model', 'model_has_fixed_addresses')->withTimestamps();
    }

    public function feedbacks(): MorphMany
    {
        return $this->morphMany(Feedback::class, 'origin');
    }


}
