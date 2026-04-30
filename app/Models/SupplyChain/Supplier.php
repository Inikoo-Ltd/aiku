<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 18 Jan 2024 19:32:25 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Models\SupplyChain;

use App\Models\GoodsIn\StockDelivery;
use App\Models\Helpers\Currency;
use App\Models\Procurement\OrgSupplier;
use App\Models\Procurement\PurchaseOrder;
use App\Models\SysAdmin\Group;
use App\Models\Traits\HasAddress;
use App\Models\Traits\HasAddresses;
use App\Models\Traits\HasAttachments;
use App\Models\Traits\HasHistory;
use App\Models\Traits\HasImage;
use App\Models\Traits\InGroup;
use Eloquent;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use App\Models\Traits\HasSearch;
use OwenIt\Auditing\Contracts\Auditable;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Collections\MediaCollection;
use Spatie\Sluggable\HasSlug;
use Spatie\Sluggable\SlugOptions;

/**
 * App\Models\SupplyChain\Supplier
 *
 * @property int $id
 * @property int $group_id
 * @property int|null $agent_id
 * @property string $scope_type Group|Organisation used to indicate private org suppliers
 * @property int $scope_id
 * @property bool $status
 * @property string $slug
 * @property string $code
 * @property string|null $name
 * @property int|null $image_id
 * @property string|null $contact_name
 * @property string|null $company_name
 * @property string|null $email
 * @property string|null $phone
 * @property string|null $identity_document_type
 * @property string|null $identity_document_number
 * @property string|null $contact_website
 * @property int|null $address_id
 * @property array<array-key, mixed> $location
 * @property int $currency_id
 * @property array<array-key, mixed> $settings
 * @property array<array-key, mixed> $data
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $archived_at
 * @property Carbon|null $fetched_at
 * @property Carbon|null $last_fetched_at
 * @property Carbon|null $deleted_at
 * @property string|null $source_slug
 * @property string|null $source_id
 * @property array<array-key, mixed> $sources
 * @property-read \App\Models\Helpers\Address|null $address
 * @property-read Collection<int, \App\Models\Helpers\Address> $addresses
 * @property-read \App\Models\SupplyChain\Agent|null $agent
 * @property-read Collection<int, \App\Models\SupplyChain\AgentSupplierPurchaseOrder> $agentSupplierPurchaseOrder
 * @property-read MediaCollection<int, \App\Models\Helpers\Media> $attachments
 * @property-read Collection<int, \App\Models\Helpers\Audit> $audits
 * @property-read Currency $currency
 * @property-read Group|null $group
 * @property-read \App\Models\Helpers\Media|null $image
 * @property-read MediaCollection<int, \App\Models\Helpers\Media> $images
 * @property-read MediaCollection<int, \App\Models\Helpers\Media> $media
 * @property-read Collection<int, OrgSupplier> $orgSuppliers
 * @property-read Collection<int, PurchaseOrder> $purchaseOrders
 * @property-read \App\Models\Helpers\Media|null $seoImage
 * @property-read \App\Models\SupplyChain\SupplierStats|null $stats
 * @property-read Collection<int, StockDelivery> $stockDeliveries
 * @property-read Collection<int, \App\Models\SupplyChain\SupplierProduct> $supplierProducts
 * @property-read Collection<int, \App\Models\SupplyChain\SupplierTimeSeries> $timeSeries
 * @method static \Database\Factories\SupplyChain\SupplierFactory factory($count = null, $state = [])
 * @method static Builder<static>|Supplier newModelQuery()
 * @method static Builder<static>|Supplier newQuery()
 * @method static Builder<static>|Supplier onlyTrashed()
 * @method static Builder<static>|Supplier query()
 * @method static Builder<static>|Supplier withTrashed(bool $withTrashed = true)
 * @method static Builder<static>|Supplier withoutTrashed()
 * @mixin Eloquent
 */
class Supplier extends Model implements HasMedia, Auditable
{
    use SoftDeletes;
    use HasAddress;
    use HasAddresses;
    use HasSlug;
    use HasImage;
    use HasFactory;
    use HasHistory;
    use HasAttachments;
    use InGroup;
    use HasSearch;

    protected $casts = [
        'data'            => 'array',
        'settings'        => 'array',
        'location'        => 'array',
        'sources'         => 'array',
        'status'          => 'boolean',
        'archived_at'     => 'datetime',
        'fetched_at'      => 'datetime',
        'last_fetched_at' => 'datetime',
    ];

    protected $attributes = [
        'data'     => '{}',
        'settings' => '{}',
        'location' => '{}',
        'sources'  => '{}',
    ];

    protected $guarded = [];

    public function searchIndexShouldBeUpdated(): bool
    {
        return $this->wasRecentlyCreated || $this->wasChanged([
                'agent_id',
                'status',
                'code',
                'name',
                'contact_name',
                'company_name',
                'email',
                'phone',
                'contact_website',
                'identity_document_number',
                'created_at'
            ]);
    }

    public function toSearchableArray(): array
    {
        return [
            'id'                       => (string)$this->id,
            'agent_id'                 => $this->agent_id,
            'status'                   => $this->status,
            'code'                     => $this->code,
            'name'                     => (string)$this->name,
            'contact_name'             => (string)$this->contact_name,
            'company_name'             => (string)$this->company_name,
            'email'                    => (string)$this->email,
            'phone'                    => (string)$this->phone,
            'contact_website'          => (string)$this->contact_website,
            'identity_document_number' => (string)$this->identity_document_number,
            'created_at'               => is_string($this->created_at) ? Carbon::parse($this->created_at)->timestamp : $this->created_at->timestamp,
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    protected static function booted(): void
    {
        static::creating(
            function (Supplier $supplier) {
                $supplier->name = $supplier->company_name == '' ? $supplier->contact_name : $supplier->company_name;
            }
        );

        static::updated(function (Supplier $supplier) {
            if (!$supplier->wasRecentlyCreated && $supplier->wasChanged(['contact_name', 'company_name'])) {
                $supplier->name = $supplier->company_name == '' ? $supplier->contact_name : $supplier->company_name;
            }
        });
    }

    public function getSlugOptions(): SlugOptions
    {
        return SlugOptions::create()
            ->generateSlugsFrom('code')
            ->doNotGenerateSlugsOnUpdate()
            ->saveSlugsTo('slug');
    }

    public function generateTags(): array
    {
        return [
            'supply-chain'
        ];
    }

    protected array $auditInclude = [
        'code',
        'name',
        'email',
        'phone',
        'contact_name',
        'company_name',
        'identity_document_type',
        'identity_document_number',
        'contact_website',
        'archived_at'
    ];

    public function stats(): HasOne
    {
        return $this->hasOne(SupplierStats::class);
    }

    public function supplierProducts(): HasMany
    {
        return $this->hasMany(SupplierProduct::class);
    }

    public function agent(): BelongsTo
    {
        return $this->belongsTo(Agent::class);
    }

    public function currency(): BelongsTo
    {
        return $this->belongsTo(Currency::class);
    }

    public function purchaseOrders(): HasMany
    {
        return $this->hasMany(PurchaseOrder::class);
    }

    public function stockDeliveries(): HasMany
    {
        return $this->hasMany(StockDelivery::class);
    }

    public function orgSuppliers(): HasMany
    {
        return $this->hasMany(OrgSupplier::class);
    }

    public function agentSupplierPurchaseOrder(): HasMany
    {
        return $this->hasMany(AgentSupplierPurchaseOrder::class);
    }

    public function timeSeries(): HasMany
    {
        return $this->hasMany(SupplierTimeSeries::class);
    }


}
