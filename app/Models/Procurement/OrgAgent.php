<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 18 Jan 2024 16:37:54 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Models\Procurement;

use App\Enums\Procurement\OrgAgent\OrgAgentOrderContainerEnum;
use App\Enums\Helpers\SerialReference\SerialReferenceModelEnum;
use App\Models\GoodsIn\StockDelivery;
use App\Models\Helpers\SerialReference;
use App\Models\SupplyChain\Agent;
use App\Models\SysAdmin\Group;
use App\Models\SysAdmin\Organisation;
use App\Models\SysAdmin\User;
use App\Models\Traits\InOrganisation;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Spatie\Sluggable\HasSlug;
use Spatie\Sluggable\SlugOptions;

/**
 * App\Models\Procurement\OrgAgent
 *
 * @property int $id
 * @property int $group_id
 * @property OrgAgentOrderContainerEnum $order_container
 * @property string|null $agent_fee_percentage share of a container's goods the agent charges as its fee; null is 2
 * @property string|null $cho_penalty_percentage points the client takes off the agent fee on the goods of an order that missed clean hand over; 1 for a new agent, null is no penalty
 * @property int $organisation_id
 * @property string $slug
 * @property int $agent_id
 * @property bool $status
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property string|null $source_id
 * @property array<string, mixed> $settings
 * @property-read Agent|null $agent
 * @property-read Group|null $group
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Procurement\OrgSupplierProduct> $orgSupplierProducts
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Procurement\OrgSupplier> $orgSuppliers
 * @property-read Organisation $organisation
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Procurement\PurchaseOrder> $purchaseOrders
 * @property-read \App\Models\Procurement\OrgAgentStats|null $stats
 * @property-read \Illuminate\Database\Eloquent\Collection<int, StockDelivery> $stockDeliveries
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OrgAgent newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OrgAgent newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OrgAgent query()
 * @mixin \Eloquent
 */
class OrgAgent extends Model
{
    use InOrganisation;
    use HasSlug;

    protected $guarded = [];

    protected $attributes = [
        'order_container' => '40ft',
        'settings'        => '{}',
    ];

    protected function casts(): array
    {
        return [
            'order_container' => OrgAgentOrderContainerEnum::class,
            'settings'        => 'array',
        ];
    }

    /**
     * @return list<int>
     */
    public function moneyRequestPayerIds(): array
    {
        return array_values(array_map('intval', Arr::get($this->settings ?? [], 'money_requests.payer_user_ids', [])));
    }

    /**
     * @return list<int>
     */
    public function moneyRequestCcIds(): array
    {
        return array_values(array_map('intval', Arr::get($this->settings ?? [], 'money_requests.cc_user_ids', [])));
    }

    public function isMoneyRequestPayer(?User $user): bool
    {
        return $user !== null && in_array($user->id, $this->moneyRequestPayerIds(), true);
    }

    public function getSlugOptions(): SlugOptions
    {
        return SlugOptions::create()
            ->generateSlugsFrom(function () {
                return $this->agent->code.'-'.$this->organisation->code;
            })
            ->saveSlugsTo('slug')
            ->doNotGenerateSlugsOnUpdate()
            ->slugsShouldBeNoLongerThan(128);
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function agent(): BelongsTo
    {
        return $this->belongsTo(Agent::class);
    }

    public function stats(): HasOne
    {
        return $this->hasOne(OrgAgentStats::class);
    }

    public function orgSuppliers(): HasMany
    {
        return $this->hasMany(OrgSupplier::class);
    }

    public function purchaseOrders(): HasMany
    {
        return $this->hasMany(PurchaseOrder::class, 'agent_id', 'agent_id')
            ->where('purchase_orders.organisation_id', $this->organisation_id);
    }

    public function serialReferences(): MorphMany
    {
        return $this->morphMany(SerialReference::class, 'container');
    }

    public function purchaseOrderSerialReference(): MorphOne
    {
        return $this->morphOne(SerialReference::class, 'container')->where('model', SerialReferenceModelEnum::PURCHASE_ORDER);
    }

    public function stockDeliveries(): MorphMany
    {
        return $this->morphMany(StockDelivery::class, 'parent');
    }

    public function orgSupplierProducts(): HasMany
    {
        return $this->hasMany(OrgSupplierProduct::class);
    }

    protected static function booted(): void
    {
        static::saved(function (OrgAgent $orgAgent) {
            $orgAgent->agent?->searchable();
        });

        static::deleted(function (OrgAgent $orgAgent) {
            $orgAgent->agent?->searchable();
        });
    }

}
