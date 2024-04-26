<?php
/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 04 Dec 2023 17:25:34 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Models\SysAdmin;

use App\Enums\Accounting\PaymentServiceProvider\PaymentServiceProviderTypeEnum;
use App\Enums\Market\ProductCategory\ProductCategoryTypeEnum;
use App\Enums\SysAdmin\Organisation\OrganisationTypeEnum;
use App\Models\Accounting\Invoice;
use App\Models\Accounting\OrgPaymentServiceProvider;
use App\Models\Accounting\Payment;
use App\Models\Accounting\PaymentAccount;
use App\Models\Accounting\PaymentServiceProvider;
use App\Models\Assets\Country;
use App\Models\Assets\Currency;
use App\Models\Assets\Language;
use App\Models\Assets\Timezone;
use App\Models\CRM\Customer;
use App\Models\CRM\Prospect;
use App\Models\Dispatch\Shipper;
use App\Models\Fulfilment\Fulfilment;
use App\Models\Fulfilment\FulfilmentCustomer;
use App\Models\Helpers\Address;
use App\Models\HumanResources\ClockingMachine;
use App\Models\HumanResources\Employee;
use App\Models\HumanResources\JobPosition;
use App\Models\HumanResources\Workplace;
use App\Models\Inventory\Location;
use App\Models\Inventory\OrgStock;
use App\Models\Inventory\Warehouse;
use App\Models\Inventory\WarehouseArea;
use App\Models\Market\CollectionCategory;
use App\Models\Market\Product;
use App\Models\Market\ProductCategory;
use App\Models\Market\Rental;
use App\Models\Market\Shop;
use App\Models\Media\Media;
use App\Models\Procurement\OrgAgent;
use App\Models\Procurement\PurchaseOrder;
use App\Models\Procurement\OrgSupplier;
use App\Models\SupplyChain\Agent;
use App\Models\Traits\HasLogo;
use App\Models\Web\Webpage;
use App\Models\Web\Website;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\Sluggable\HasSlug;
use Spatie\Sluggable\SlugOptions;

/**
 * App\Models\SysAdmin\Organisation
 *
 * @property int $id
 * @property int $group_id
 * @property string $ulid
 * @property OrganisationTypeEnum $type
 * @property string $slug
 * @property string $code
 * @property string $name
 * @property string|null $email
 * @property string|null $phone
 * @property bool $status
 * @property int|null $address_id
 * @property array $location
 * @property array $data
 * @property array $settings
 * @property array $source
 * @property int $country_id
 * @property int $language_id
 * @property int $timezone_id
 * @property int $currency_id customer accounting currency
 * @property int|null $logo_id
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property string|null $deleted_at
 * @property-read \App\Models\SysAdmin\OrganisationAccountingStats|null $accountingStats
 * @property-read Address|null $address
 * @property-read Agent|null $agent
 * @property-read Collection<int, \App\Models\SysAdmin\OrganisationAuthorisedModels> $authorisedModels
 * @property-read Collection<int, ClockingMachine> $clockingMachines
 * @property-read Collection<int, CollectionCategory> $collectionCategories
 * @property-read Country $country
 * @property-read \App\Models\SysAdmin\OrganisationCRMStats|null $crmStats
 * @property-read Currency $currency
 * @property-read Collection<int, Customer> $customers
 * @property-read Collection<int, Employee> $employees
 * @property-read Collection<int, FulfilmentCustomer> $fulfilmentCustomers
 * @property-read \App\Models\SysAdmin\OrganisationFulfilmentStats|null $fulfilmentStats
 * @property-read Collection<int, Fulfilment> $fulfilments
 * @property-read \App\Models\SysAdmin\Group $group
 * @property-read \App\Models\SysAdmin\OrganisationHumanResourcesStats|null $humanResourcesStats
 * @property-read \App\Models\SysAdmin\OrganisationInventoryStats|null $inventoryStats
 * @property-read Collection<int, Invoice> $invoices
 * @property-read Collection<int, JobPosition> $josPositions
 * @property-read Language $language
 * @property-read Collection<int, Location> $locations
 * @property-read Media|null $logo
 * @property-read \App\Models\SysAdmin\OrganisationMailStats|null $mailStats
 * @property-read \App\Models\SysAdmin\OrganisationMarketStats|null $marketStats
 * @property-read \Spatie\MediaLibrary\MediaCollections\Models\Collections\MediaCollection<int, Media> $media
 * @property-read Collection<int, OrgAgent> $orgAgents
 * @property-read Collection<int, OrgPaymentServiceProvider> $orgPaymentServiceProviders
 * @property-read Collection<int, OrgStock> $orgStocks
 * @property-read Collection<int, OrgSupplier> $orgSuppliers
 * @property-read Collection<int, PaymentAccount> $paymentAccounts
 * @property-read Collection<int, PaymentServiceProvider> $paymentServiceProviders
 * @property-read Collection<int, Payment> $payments
 * @property-read \App\Models\SysAdmin\OrganisationProcurementStats|null $procurementStats
 * @property-read Collection<int, ProductCategory> $productCategories
 * @property-read \App\Models\SysAdmin\OrganisationProductionStats|null $productionStats
 * @property-read Collection<int, Product> $products
 * @property-read Collection<int, Prospect> $prospects
 * @property-read Collection<int, PurchaseOrder> $purchaseOrders
 * @property-read Collection<int, Rental> $rentals
 * @property-read Collection<int, \App\Models\SysAdmin\Role> $roles
 * @property-read \App\Models\SysAdmin\OrganisationSalesStats|null $salesStats
 * @property-read Collection<int, Shipper> $shippers
 * @property-read Collection<int, Shop> $shops
 * @property-read \App\Models\SysAdmin\OrganisationStats|null $stats
 * @property-read Timezone $timezone
 * @property-read Collection<int, WarehouseArea> $warehouseAreas
 * @property-read Collection<int, Warehouse> $warehouses
 * @property-read \App\Models\SysAdmin\OrganisationWebStats|null $webStats
 * @property-read Collection<int, Webpage> $webpages
 * @property-read Collection<int, Website> $websites
 * @property-read Collection<int, Workplace> $workplaces
 * @method static \Database\Factories\SysAdmin\OrganisationFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder|Organisation newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|Organisation newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|Organisation query()
 * @mixin \Eloquent
 */
class Organisation extends Model implements HasMedia
{
    use HasFactory;
    use HasSlug;
    use InteractsWithMedia;
    use HasLogo;

    protected $casts = [
        'data'     => 'array',
        'settings' => 'array',
        'source'   => 'array',
        'location' => 'array',
        'type'     => OrganisationTypeEnum::class
    ];

    protected $attributes = [
        'data'     => '{}',
        'settings' => '{}',
        'source'   => '{}',
        'location' => '{}'
    ];

    protected $guarded = [];

    public function getSlugOptions(): SlugOptions
    {
        return SlugOptions::create()
            ->generateSlugsFrom('code')
            ->doNotGenerateSlugsOnUpdate()
            ->saveSlugsTo('slug');
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function address(): BelongsTo
    {
        return $this->belongsTo(Address::class);
    }

    public function employees(): HasMany
    {
        return $this->hasMany(Employee::class);
    }

    public function josPositions(): HasMany
    {
        return $this->hasMany(JobPosition::class);
    }

    public function workplaces(): HasMany
    {
        return $this->hasMany(Workplace::class);
    }

    public function stats(): HasOne
    {
        return $this->hasOne(OrganisationStats::class);
    }

    public function humanResourcesStats(): HasOne
    {
        return $this->hasOne(OrganisationHumanResourcesStats::class);
    }

    public function procurementStats(): HasOne
    {
        return $this->hasOne(OrganisationProcurementStats::class);
    }

    public function inventoryStats(): HasOne
    {
        return $this->hasOne(OrganisationInventoryStats::class);
    }

    public function productionStats(): HasOne
    {
        return $this->hasOne(OrganisationProductionStats::class);
    }

    public function fulfilmentStats(): HasOne
    {
        return $this->hasOne(OrganisationFulfilmentStats::class);
    }

    public function marketStats(): HasOne
    {
        return $this->hasOne(OrganisationMarketStats::class);
    }

    public function mailStats(): HasOne
    {
        return $this->hasOne(OrganisationMailStats::class);
    }

    public function salesStats(): HasOne
    {
        return $this->hasOne(OrganisationSalesStats::class);
    }

    public function crmStats(): HasOne
    {
        return $this->hasOne(OrganisationCRMStats::class);
    }

    public function webStats(): HasOne
    {
        return $this->hasOne(OrganisationWebStats::class);
    }

    public function accountingStats(): HasOne
    {
        return $this->hasOne(OrganisationAccountingStats::class);
    }

    public function currency(): BelongsTo
    {
        return $this->belongsTo(Currency::class);
    }

    public function country(): BelongsTo
    {
        return $this->belongsTo(Country::class);
    }

    public function timezone(): BelongsTo
    {
        return $this->belongsTo(Timezone::class);
    }

    public function language(): BelongsTo
    {
        return $this->belongsTo(Language::class);
    }

    public function accountsServiceProvider(): OrgPaymentServiceProvider
    {
        return OrgPaymentServiceProvider::where('organisation_id', $this->id)->where('type', PaymentServiceProviderTypeEnum::ACCOUNT)->first();
    }

    public function group(): BelongsTo
    {
        return $this->belongsTo(Group::class, 'group_id');
    }

    public function logo(): HasOne
    {
        return $this->hasOne(Media::class, 'id', 'logo_id');
    }

    public function shops(): HasMany
    {
        return $this->hasMany(Shop::class);
    }

    public function warehouses(): HasMany
    {
        return $this->hasMany(Warehouse::class);
    }

    public function warehouseAreas(): HasMany
    {
        return $this->hasMany(WarehouseArea::class);
    }

    public function locations(): HasMany
    {
        return $this->hasMany(Location::class);
    }

    public function purchaseOrders(): HasMany
    {
        return $this->hasMany(PurchaseOrder::class);
    }

    public function shippers(): HasMany
    {
        return $this->hasMany(Shipper::class);
    }

    public function clockingMachines(): HasMany
    {
        return $this->hasMany(ClockingMachine::class);
    }

    public function orgPaymentServiceProviders(): HasMany
    {
        return $this->hasMany(OrgPaymentServiceProvider::class);
    }

    public function paymentServiceProviders(): HasMany
    {
        return $this->hasMany(PaymentServiceProvider::class);
    }

    public function paymentAccounts(): HasMany
    {
        return $this->hasMany(PaymentAccount::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('logo')
            ->singleFile();
    }

    public function roles(): MorphMany
    {
        return $this->morphMany(Role::class, 'scope');
    }

    public function authorisedModels(): HasMany
    {
        return $this->hasMany(OrganisationAuthorisedModels::class, 'org_id');
    }

    public function customers(): HasMany
    {
        return $this->hasMany(Customer::class);
    }

    public function fulfilmentCustomers(): HasMany
    {
        return $this->hasMany(FulfilmentCustomer::class);
    }

    public function prospects(): HasMany
    {
        return $this->hasMany(Prospect::class);
    }

    public function websites(): HasMany
    {
        return $this->hasMany(Website::class);
    }

    public function webpages(): HasMany
    {
        return $this->hasMany(Webpage::class);
    }

    public function orgAgents(): HasMany
    {
        return $this->hasMany(OrgAgent::class);
    }

    public function orgSuppliers(): HasMany
    {
        return $this->hasMany(OrgSupplier::class);
    }

    public function agent(): HasOne
    {
        return $this->hasOne(Agent::class);
    }

    public function orgStocks(): HasMany
    {
        return $this->hasMany(OrgStock::class);
    }

    public function fulfilments(): HasMany
    {
        return $this->hasMany(Fulfilment::class);
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }

    public function productCategories(): HasMany
    {
        return $this->hasMany(ProductCategory::class);
    }

    public function departments(): Collection
    {
        return $this->productCategories()->where('type', ProductCategoryTypeEnum::DEPARTMENT)->get();
    }

    public function families(): ?Collection
    {
        return $this->productCategories()->where('type', ProductCategoryTypeEnum::FAMILY)->get();
    }

    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    public function rentals(): HasMany
    {
        return $this->hasMany(Rental::class);
    }

    public function collectionCategories(): HasMany
    {
        return $this->hasMany(CollectionCategory::class);
    }

    public function collections(): HasMany
    {
        return $this->hasMany(Collection::class);
    }
}
