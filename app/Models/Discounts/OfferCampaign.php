<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 10 Sept 2024 14:42:29 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Models\Discounts;

use App\Enums\Discounts\OfferCampaign\OfferCampaignStateEnum;
use App\Enums\Discounts\OfferCampaign\OfferCampaignTypeEnum;
use App\Models\Accounting\InvoiceTransaction;
use App\Models\Ordering\Transaction;
use App\Models\Traits\HasHistory;
use App\Models\Traits\HasUniversalSearch;
use App\Models\Traits\InShop;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
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
 * @property OfferCampaignStateEnum $state
 * @property bool $status
 * @property string $slug
 * @property string $code
 * @property string $name
 * @property OfferCampaignTypeEnum $type
 * @property array<array-key, mixed> $data
 * @property array<array-key, mixed> $settings
 * @property string|null $start_at
 * @property string|null $finish_at
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property string|null $fetched_at
 * @property string|null $last_fetched_at
 * @property \Illuminate\Support\Carbon|null $deleted_at
 * @property string|null $source_id
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Helpers\Audit> $audits
 * @property-read \App\Models\SysAdmin\Group $group
 * @property-read \Illuminate\Database\Eloquent\Collection<int, InvoiceTransaction> $invoiceTransactions
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Discounts\OfferComponent> $offerComponents
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Discounts\Offer> $offers
 * @property-read \App\Models\SysAdmin\Organisation $organisation
 * @property-read \App\Models\Catalogue\Shop $shop
 * @property-read \App\Models\Discounts\OfferCampaignStats|null $stats
 * @property-read \Illuminate\Database\Eloquent\Collection<int, Transaction> $transactions
 * @property-read \App\Models\Helpers\UniversalSearch|null $universalSearch
 * @method static \Database\Factories\Discounts\OfferCampaignFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OfferCampaign newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OfferCampaign newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OfferCampaign onlyTrashed()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OfferCampaign query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OfferCampaign withTrashed(bool $withTrashed = true)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OfferCampaign withoutTrashed()
 * @mixin \Eloquent
 */
class OfferCampaign extends Model implements Auditable
{
    use SoftDeletes;
    use HasSlug;
    use HasFactory;
    use InShop;
    use HasHistory;
    use HasUniversalSearch;


    protected $casts = [
        'data'     => 'array',
        'settings' => 'array',
        'state'    => OfferCampaignStateEnum::class,
        'type'     => OfferCampaignTypeEnum::class
    ];

    protected $attributes = [
        'data'     => '{}',
        'settings' => '{}'
    ];

    protected $guarded = [];

    public function generateTags(): array
    {
        return ['discounts'];
    }

    protected array $auditInclude = [
        'name',
        'type',
        'status',
        'state',
    ];

    public function getSlugOptions(): SlugOptions
    {
        return SlugOptions::create()
            ->generateSlugsFrom(function () {
                return $this->code.' '.$this->shop->code;
            })
            ->doNotGenerateSlugsOnUpdate()
            ->saveSlugsTo('slug')
            ->slugsShouldBeNoLongerThan(128);
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function offers(): HasMany
    {
        return $this->hasMany(Offer::class);
    }

    public function offerComponents(): HasMany
    {
        return $this->hasMany(OfferComponent::class);
    }

    public function stats(): HasOne
    {
        return $this->hasOne(OfferCampaignStats::class);
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
