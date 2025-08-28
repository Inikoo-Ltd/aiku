<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 10 Sept 2024 21:54:57 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Models\Discounts;

use App\Enums\Discounts\OfferComponent\OfferComponentStateEnum;
use App\Models\Accounting\InvoiceTransaction;
use App\Models\Ordering\Transaction;
use App\Models\Traits\HasHistory;
use App\Models\Traits\InShop;
use Eloquent;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
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
 * @property int $offer_id
 * @property OfferComponentStateEnum $state
 * @property bool $status
 * @property string $slug
 * @property string $code
 * @property array<array-key, mixed> $data
 * @property string|null $trigger_scope
 * @property string|null $trigger_type
 * @property string|null $trigger_id
 * @property string|null $target_type
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
 * @property-read \App\Models\Discounts\Offer $offer
 * @property-read \App\Models\Discounts\OfferCampaign $offerCampaign
 * @property-read \App\Models\SysAdmin\Organisation $organisation
 * @property-read \App\Models\Catalogue\Shop $shop
 * @property-read \App\Models\Discounts\OfferComponentStats|null $stats
 * @property-read \Illuminate\Database\Eloquent\Collection<int, Transaction> $transactions
 * @method static \Database\Factories\Discounts\OfferComponentFactory factory($count = null, $state = [])
 * @method static Builder<static>|OfferComponent newModelQuery()
 * @method static Builder<static>|OfferComponent newQuery()
 * @method static Builder<static>|OfferComponent onlyTrashed()
 * @method static Builder<static>|OfferComponent query()
 * @method static Builder<static>|OfferComponent withTrashed(bool $withTrashed = true)
 * @method static Builder<static>|OfferComponent withoutTrashed()
 * @mixin Eloquent
 */
class OfferComponent extends Model implements Auditable
{
    use SoftDeletes;
    use HasSlug;
    use HasFactory;
    use HasHistory;
    use InShop;

    protected $casts = [
        'data'            => 'array',
        'source_data'     => 'array',
        'state'           => OfferComponentStateEnum::class,
        'begin_at'        => 'datetime',
        'end_at'          => 'datetime',
        'fetched_at'      => 'datetime',
        'last_fetched_at' => 'datetime',
    ];

    protected $attributes = [
        'data'        => '{}',
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
        'state',
    ];

    public function getSlugOptions(): SlugOptions
    {
        return SlugOptions::create()

            ->generateSlugsFrom(function () {
                return $this->code.'-'.$this->offer->slug;
            })

            ->doNotGenerateSlugsOnUpdate()
            ->saveSlugsTo('slug')
            ->slugsShouldBeNoLongerThan(128);
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function offerCampaign(): BelongsTo
    {
        return $this->belongsTo(OfferCampaign::class);
    }

    public function offer(): BelongsTo
    {
        return $this->belongsTo(Offer::class);
    }

    public function stats(): HasOne
    {
        return $this->hasOne(OfferComponentStats::class);
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
