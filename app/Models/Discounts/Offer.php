<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 10 Sept 2024 21:54:57 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Models\Discounts;

use App\Enums\Discounts\Offer\OfferStateEnum;
use App\Models\Accounting\InvoiceTransaction;
use App\Models\Ordering\Transaction;
use App\Models\Traits\HasHistory;
use App\Models\Traits\HasUniversalSearch;
use App\Models\Traits\InShop;
use Eloquent;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use OwenIt\Auditing\Contracts\Auditable;
use Spatie\Sluggable\HasSlug;
use Spatie\Sluggable\SlugOptions;

/**
 * @property int $id
 * @property int $group_id
 * @property int $organisation_id
 * @property int $shop_id
 * @property int $offer_campaign_id
 * @property OfferStateEnum $state
 * @property bool $status
 * @property string $slug
 * @property string $code
 * @property string $name
 * @property string $type
 * @property string|null $trigger_type
 * @property int|null $trigger_id
 * @property array<array-key, mixed> $allowances
 * @property array<array-key, mixed> $data
 * @property array<array-key, mixed> $settings
 * @property bool $is_discretionary
 * @property bool $is_locked
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property string|null $start_at
 * @property \Illuminate\Support\Carbon|null $end_at
 * @property \Illuminate\Support\Carbon|null $fetched_at
 * @property \Illuminate\Support\Carbon|null $last_fetched_at
 * @property \Illuminate\Support\Carbon|null $deleted_at
 * @property string|null $source_id
 * @property array<array-key, mixed> $source_data
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Helpers\Audit> $audits
 * @property-read \App\Models\SysAdmin\Group $group
 * @property-read \Illuminate\Database\Eloquent\Collection<int, InvoiceTransaction> $invoiceTransactions
 * @property-read \App\Models\Discounts\OfferCampaign $offerCampaign
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Discounts\OfferComponent> $offerComponents
 * @property-read \App\Models\SysAdmin\Organisation $organisation
 * @property-read \App\Models\Catalogue\Shop $shop
 * @property-read \App\Models\Discounts\OfferStats|null $stats
 * @property-read \Illuminate\Database\Eloquent\Collection<int, Transaction> $transactions
 * @property-read \App\Models\Helpers\UniversalSearch|null $universalSearch
 * @method static \Database\Factories\Discounts\OfferFactory factory($count = null, $state = [])
 * @method static Builder<static>|Offer newModelQuery()
 * @method static Builder<static>|Offer newQuery()
 * @method static Builder<static>|Offer onlyTrashed()
 * @method static Builder<static>|Offer query()
 * @method static Builder<static>|Offer withTrashed(bool $withTrashed = true)
 * @method static Builder<static>|Offer withoutTrashed()
 * @mixin Eloquent
 */
class Offer extends Model implements Auditable
{
    use SoftDeletes;
    use HasSlug;
    use HasFactory;
    use InShop;
    use HasHistory;
    use HasUniversalSearch;

    protected $casts = [
        'data'            => 'array',
        'settings'        => 'array',
        'allowances'      => 'array',
        'source_data'     => 'array',
        'begin_at'        => 'datetime',
        'end_at'          => 'datetime',
        'fetched_at'      => 'datetime',
        'last_fetched_at' => 'datetime',
        'status'          => 'boolean',
        'state'           => OfferStateEnum::class,
    ];

    protected $attributes = [
        'data'        => '{}',
        'settings'    => '{}',
        'allowances'  => '{}',
        'source_data' => '{}',
    ];

    protected $guarded = [];

    public function generateTags(): array
    {
        return ['discounts'];
    }

    protected array $auditInclude = [
        'code',
        'name',
        'type',
        'status',
        'state',
    ];


    public function getSlugOptions(): SlugOptions
    {
        return SlugOptions::create()
            ->generateSlugsFrom('code')
            ->doNotGenerateSlugsOnUpdate()
            ->saveSlugsTo('slug')
            ->slugsShouldBeNoLongerThan(128);
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function stats(): HasOne
    {
        return $this->hasOne(OfferStats::class);
    }

    public function offerCampaign(): BelongsTo
    {
        return $this->belongsTo(OfferCampaign::class);
    }

    public function offerComponents(): HasMany
    {
        return $this->hasMany(OfferComponent::class);
    }

    public function transactions(): BelongsToMany
    {
        return $this->belongsToMany(Transaction::class, 'transaction_has_offer_components');
    }

    public function invoiceTransactions(): BelongsToMany
    {
        return $this->belongsToMany(InvoiceTransaction::class, 'invoice_transaction_has_offer_components');
    }

}
