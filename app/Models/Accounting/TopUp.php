<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 08 Aug 2024 10:16:16 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Models\Accounting;

use App\Enums\Accounting\TopUp\TopUpStatusEnum;
use App\Models\Helpers\Currency;
use App\Models\Traits\HasHistory;
use App\Models\Traits\HasUniversalSearch;
use App\Models\Traits\InCustomer;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use OwenIt\Auditing\Contracts\Auditable;
use Spatie\Sluggable\HasSlug;
use Spatie\Sluggable\SlugOptions;

/**
 * @property int $id
 * @property int $group_id
 * @property int $organisation_id
 * @property string|null $slug
 * @property string $reference
 * @property int $shop_id
 * @property int $customer_id
 * @property int $payment_id
 * @property TopUpStatusEnum $status
 * @property numeric $amount
 * @property int $currency_id
 * @property numeric|null $grp_amount
 * @property numeric|null $org_amount
 * @property array<array-key, mixed> $data
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property string|null $fetched_at
 * @property string|null $last_fetched_at
 * @property string|null $source_id
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Helpers\Audit> $audits
 * @property-read \App\Models\Accounting\CreditTransaction|null $creditTransaction
 * @property-read Currency $currency
 * @property-read \App\Models\CRM\Customer $customer
 * @property-read \App\Models\SysAdmin\Group $group
 * @property-read \App\Models\SysAdmin\Organisation $organisation
 * @property-read \App\Models\Accounting\Payment $payment
 * @property-read \App\Models\Catalogue\Shop $shop
 * @property-read \App\Models\Helpers\UniversalSearch|null $universalSearch
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TopUp newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TopUp newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TopUp query()
 * @mixin \Eloquent
 */
class TopUp extends Model implements Auditable
{
    use HasSlug;
    use HasUniversalSearch;
    use InCustomer;
    use HasHistory;

    protected $casts = [
        'status'         => TopUpStatusEnum::class,
        'data'           => 'array',
        'amount'         => 'decimal:2',
        'grp_amount'     => 'decimal:2',
        'org_amount'     => 'decimal:2',

    ];

    protected $attributes = [
        'data' => '{}',
    ];

    protected $guarded = [];

    public function generateTags(): array
    {
        return ['accounting'];
    }

    protected array $auditInclude = [
        'reference',
        'status',
        'amount',
    ];

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function getSlugOptions(): SlugOptions
    {
        return SlugOptions::create()
            ->generateSlugsFrom('reference')
            ->saveSlugsTo('slug')
            ->doNotGenerateSlugsOnUpdate();
    }

    public function currency(): BelongsTo
    {
        return $this->belongsTo(Currency::class);
    }

    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }

    public function creditTransaction(): HasOne
    {
        return $this->hasOne(CreditTransaction::class);
    }

}
