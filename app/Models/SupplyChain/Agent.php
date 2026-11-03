<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 18 Jan 2024 19:31:15 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Models\SupplyChain;

use App\Enums\Procurement\PurchaseOrder\PurchaseOrderJourneyStageEnum;
use App\Models\GoodsIn\StockDelivery;
use App\Models\Procurement\OrgAgent;
use App\Models\Procurement\OrgSupplier;
use App\Models\Procurement\PurchaseOrder;
use App\Models\SysAdmin\Group;
use App\Models\SysAdmin\Organisation;
use App\Models\Traits\HasAttachments;
use App\Models\Traits\HasHistory;
use App\Models\Traits\HasImage;
use App\Models\Traits\HasSearch;
use App\Models\Traits\InGroup;
use Eloquent;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use OwenIt\Auditing\Contracts\Auditable;
use Spatie\MediaLibrary\HasMedia;
use Spatie\Sluggable\HasSlug;
use Spatie\Sluggable\SlugOptions;

/**
 * App\Models\SupplyChain\Agent
 *
 * @property int $id
 * @property int $group_id
 * @property int $organisation_id
 * @property string $code mirror of organisation
 * @property string $name mirror of organisation
 * @property bool $status
 * @property string $slug
 * @property int|null $image_id
 * @property \Illuminate\Support\Carbon|null $fetched_at
 * @property \Illuminate\Support\Carbon|null $last_fetched_at
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property \Illuminate\Support\Carbon|null $deleted_at
 * @property string|null $source_slug
 * @property string|null $source_id
 * @property array<array-key, mixed> $sources
 * @property-read \Spatie\MediaLibrary\MediaCollections\Models\Collections\MediaCollection<int, \App\Models\Helpers\Media> $attachments
 * @property-read Collection<int, \App\Models\Helpers\Audit> $audits
 * @property-read Group|null $group
 * @property-read \App\Models\Helpers\Media|null $image
 * @property-read \Spatie\MediaLibrary\MediaCollections\Models\Collections\MediaCollection<int, \App\Models\Helpers\Media> $images
 * @property-read \Spatie\MediaLibrary\MediaCollections\Models\Collections\MediaCollection<int, \App\Models\Helpers\Media> $media
 * @property-read Collection<int, OrgAgent> $orgAgents
 * @property-read Collection<int, OrgSupplier> $orgSuppliers
 * @property-read Organisation $organisation
 * @property-read Collection<int, PurchaseOrder> $purchaseOrders
 * @property-read \App\Models\Helpers\Media|null $seoImage
 * @property-read \App\Models\SupplyChain\AgentStats|null $stats
 * @property-read Collection<int, StockDelivery> $stockDeliveries
 * @property-read Collection<int, \App\Models\SupplyChain\SupplierProduct> $supplierProducts
 * @property-read Collection<int, \App\Models\SupplyChain\Supplier> $suppliers
 * @property-read Collection<int, \App\Models\SupplyChain\AgentTimeSeries> $timeSeries
 * @method static \Database\Factories\SupplyChain\AgentFactory factory($count = null, $state = [])
 * @method static Builder<static>|Agent newModelQuery()
 * @method static Builder<static>|Agent newQuery()
 * @method static Builder<static>|Agent onlyTrashed()
 * @method static Builder<static>|Agent query()
 * @method static Builder<static>|Agent withTrashed(bool $withTrashed = true)
 * @method static Builder<static>|Agent withoutTrashed()
 * @mixin Eloquent
 */
class Agent extends Model implements HasMedia, Auditable
{
    use SoftDeletes;
    use HasSlug;
    use HasFactory;
    use HasHistory;
    use HasImage;
    use HasAttachments;
    use InGroup;
    use HasSearch;

    protected $casts = [
        'status'          => 'boolean',
        'fetched_at'      => 'datetime',
        'last_fetched_at' => 'datetime',
        'sources'         => 'array',
        'data'            => 'array',
        'settings'        => 'array',
    ];

    protected $attributes = [
        'sources'  => '{}',
        'data'     => '{}',
        'settings' => '{}',
    ];

    protected $guarded = [];

    public function generateTags(): array
    {
        return [
            'supply-chain'
        ];
    }

    protected array $auditInclude = [
        'code',
        'name',
        'status',
    ];

    public function searchIndexShouldBeUpdated(): bool
    {
        return $this->wasRecentlyCreated || $this->wasChanged(['code', 'name']);
    }

    public function toSearchableArray(): array
    {
        return [
            'id'               => (string)$this->id,
            'code'             => $this->code,
            'name'             => (string)$this->name,
            'slug'             => $this->slug,
            'created_at'       => is_string($this->created_at) ? Carbon::parse($this->created_at)->timestamp : $this->created_at->timestamp,
            'organisation_ids' => $this->orgAgents()->pluck('organisation_id')->all(),
        ];
    }

    public function getSlugOptions(): SlugOptions
    {
        return SlugOptions::create()
            ->generateSlugsFrom(function () {
                return $this->organisation->slug;
            })
            ->doNotGenerateSlugsOnUpdate()
            ->saveSlugsTo('slug');
    }

    // Note: this a one-to-one relationship (agent is an extension of an organisation)
    public function organisation(): BelongsTo
    {
        return $this->belongsTo(Organisation::class);
    }

    public function stats(): HasOne
    {
        return $this->hasOne(AgentStats::class);
    }

    public function suppliers(): HasMany
    {
        return $this->hasMany(Supplier::class);
    }

    public function supplierProducts(): HasMany
    {
        return $this->hasMany(SupplierProduct::class);
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    /**
     * The agent's suppliers deliver to the agent's own warehouse, where each delivery is recorded as a goods receipt
     * before anything goes in a container.
     */
    public function receivesGoodsInOwnWarehouse(): bool
    {
        return (bool) Arr::get($this->settings, 'receives_goods_in_own_warehouse', false);
    }

    public const array CHO_MODES = ['next_container', 'forwarder'];

    public const array READY_CHECKS = ['balance', 'packing', 'qc', 'compliance'];

    /**
     * How this agent's purchase orders move through the PO journey, from settings.po_journey with defaults applied.
     * Clean Hand Over (cho_mode) is done once every item is in a container (next_container, the default for an agent
     * that receives goods in its own warehouse) or once the agent hands the goods to the shipping agent (forwarder).
     * Per stage, a null label or description falls back to the default wording and null days to the standard length
     * of the stage; raw keeps the nulls so a form can tell what was set.
     *
     * @return array{cho_mode: string, stages: array<string, array{label: ?string, description: ?string, days: ?int}>, ready_checks: string[]}
     */
    public function poJourney(bool $raw = false): array
    {
        return self::poJourneyFromSettings($this->settings, $raw);
    }

    /**
     * @return array{cho_mode: string, stages: array<string, array{label: ?string, description: ?string, days: ?int}>, ready_checks: string[]}
     */
    public static function poJourneyFromSettings(?array $settings, bool $raw = false): array
    {
        $stored = Arr::get($settings ?? [], 'po_journey', []);
        $mode   = Arr::get($stored, 'cho_mode');
        $labels = PurchaseOrderJourneyStageEnum::labels();
        $texts  = PurchaseOrderJourneyStageEnum::descriptions();

        $stages = [];
        foreach (PurchaseOrderJourneyStageEnum::values() as $key) {
            $label = Arr::get($stored, "stages.$key.label");
            $text  = Arr::get($stored, "stages.$key.description");
            $days  = Arr::get($stored, "stages.$key.days");

            $stages[$key] = [
                'label'       => filled($label) ? (string) $label : ($raw ? null : $labels[$key]),
                'description' => filled($text) ? (string) $text : ($raw ? null : $texts[$key]),
                'days'        => is_numeric($days) ? (int) $days : null,
            ];
        }

        $checks = Arr::get($stored, 'ready_checks');

        return [
            'cho_mode'     => in_array($mode, self::CHO_MODES, true)
                ? $mode
                : ((bool) Arr::get($settings ?? [], 'receives_goods_in_own_warehouse', false) ? 'next_container' : 'forwarder'),
            'stages'       => $stages,
            'ready_checks' => is_array($checks) ? array_values(array_intersect(self::READY_CHECKS, $checks)) : self::READY_CHECKS,
        ];
    }

    public function purchaseOrders(): HasMany
    {
        return $this->hasMany(PurchaseOrder::class);
    }

    public function stockDeliveries(): HasMany
    {
        return $this->hasMany(StockDelivery::class);
    }

    public function orgAgents(): HasMany
    {
        return $this->hasMany(OrgAgent::class);
    }

    public function orgSuppliers(): HasMany
    {
        return $this->hasMany(OrgSupplier::class);
    }

    public function timeSeries(): HasMany
    {
        return $this->hasMany(AgentTimeSeries::class);
    }

    public function deposits(): HasMany
    {
        return $this->hasMany(AspoDeposit::class);
    }

    public function depositRequests(): HasMany
    {
        return $this->hasMany(DepositRequest::class);
    }

}
