<?php
/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 23 Apr 2023 11:32:21 Malaysia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Models\Tenancy;

use App\Models\Accounting\PaymentServiceProvider;
use App\Models\Assets\Currency;
use App\Models\Central\CentralDomain;
use App\Models\Central\CentralMedia;
use App\Models\Inventory\Stock;
use App\Models\Procurement\Agent;
use App\Models\Procurement\Supplier;
use App\Models\SysAdmin\AdminUser;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Spatie\Multitenancy\Models\Tenant as SpatieTenant;
use Spatie\Multitenancy\TenantCollection;

/**
 * App\Models\Tenancy\Tenant
 *
 * @property int $id
 * @property string $ulid
 * @property string $slug
 * @property string $code
 * @property string $name
 * @property bool $status
 * @property array $data
 * @property array $source
 * @property int $country_id
 * @property int $language_id
 * @property int $timezone_id
 * @property int $currency_id tenant accounting currency
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property string|null $deleted_at
 * @property-read \App\Models\Tenancy\TenantAccountingStats|null $accountingStats
 * @property-read \App\Models\SysAdmin\AdminUser|null $adminUser
 * @property-read \Illuminate\Database\Eloquent\Collection<int, Agent> $agents
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Central\CentralDomain> $centralDomains
 * @property-read \Spatie\MediaLibrary\MediaCollections\Models\Collections\MediaCollection<int, \App\Models\Central\CentralMedia> $centralMedia
 * @property-read Currency $currency
 * @property-read \App\Models\Tenancy\TenantFulfilmentStats|null $fulfilmentStats
 * @property-read \App\Models\Tenancy\TenantInventoryStats|null $inventoryStats
 * @property-read \App\Models\Tenancy\TenantMailStats|null $mailStats
 * @property-read \App\Models\Tenancy\TenantMarketingStats|null $marketingStats
 * @property-read \App\Models\Tenancy\TenantProcurementStats|null $procurementStats
 * @property-read \App\Models\Tenancy\TenantProductionStats|null $productionStats
 * @property-read \App\Models\Tenancy\TenantSalesStats|null $salesStats
 * @property-read \App\Models\Tenancy\TenantStats|null $stats
 * @property-read \Illuminate\Database\Eloquent\Collection<int, Stock> $stocks
 * @property-read \Illuminate\Database\Eloquent\Collection<int, Supplier> $suppliers
 * @method static TenantCollection<int, static> all($columns = ['*'])
 * @method static TenantCollection<int, static> get($columns = ['*'])
 * @method static Builder|Tenant newModelQuery()
 * @method static Builder|Tenant newQuery()
 * @method static Builder|Tenant query()
 * @mixin \Eloquent
 */
class Tenant extends SpatieTenant
{
    protected $casts = [
        'data'   => 'array',
        'source' => 'array',
    ];

    protected $attributes = [
        'data'   => '{}',
        'source' => '{}',
    ];

    protected $guarded = [];

    public function getDatabaseName(): string
    {
        return 'aiku_'.$this->code;
    }

    public function stats(): HasOne
    {
        return $this->hasOne(TenantStats::class);
    }

    public function procurementStats(): HasOne
    {
        return $this->hasOne(TenantProcurementStats::class);
    }

    public function inventoryStats(): HasOne
    {
        return $this->hasOne(TenantInventoryStats::class);
    }

    public function productionStats(): HasOne
    {
        return $this->hasOne(TenantProductionStats::class);
    }

    public function fulfilmentStats(): HasOne
    {
        return $this->hasOne(TenantFulfilmentStats::class);
    }

    public function marketingStats(): HasOne
    {
        return $this->hasOne(TenantMarketingStats::class);
    }

    public function mailStats(): HasOne
    {
        return $this->hasOne(TenantMailStats::class);
    }

    public function salesStats(): HasOne
    {
        return $this->hasOne(TenantSalesStats::class);
    }

    public function accountingStats(): HasOne
    {
        return $this->hasOne(TenantAccountingStats::class);
    }

    public function currency(): BelongsTo
    {
        return $this->belongsTo(Currency::class);
    }

    public function centralDomains(): HasMany
    {
        return $this->hasMany(CentralDomain::class);
    }

    public function suppliers(): MorphMany
    {
        return $this->morphMany(Supplier::class, 'owner');
    }

    public function agents(): HasMany
    {
        return $this->hasMany(Agent::class, 'owner_id');
    }

    public function stocks(): MorphMany
    {
        return $this->morphMany(Stock::class, 'owner');
    }

    public function adminUser(): MorphOne
    {
        return $this->morphOne(AdminUser::class, 'userable');
    }

    public function accountsServiceProvider(): PaymentServiceProvider
    {
        return PaymentServiceProvider::where('data->service-code', 'accounts')->first();
    }

    public function centralMedia(): BelongsToMany
    {
        return $this->belongsToMany(CentralMedia::class)->withTimestamps();
    }

    public function group(): BelongsTo
    {
        return $this->belongsTo(Group::class, 'group_id');
    }
}
