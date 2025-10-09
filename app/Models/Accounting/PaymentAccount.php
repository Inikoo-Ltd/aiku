<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 27 Feb 2023 10:07:25 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Models\Accounting;

use App\Enums\Accounting\PaymentAccount\PaymentAccountTypeEnum;
use App\Models\Helpers\SerialReference;
use App\Models\SysAdmin\Organisation;
use App\Models\Traits\HasHistory;
use App\Models\Traits\HasUniversalSearch;
use App\Models\Traits\InOrganisation;
use Eloquent;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use OwenIt\Auditing\Contracts\Auditable;
use Spatie\Sluggable\HasSlug;
use Spatie\Sluggable\SlugOptions;

/**
 * App\Models\Accounting\PaymentAccount
 *
 * @property int $id
 * @property string $slug
 * @property int $group_id
 * @property int $organisation_id
 * @property int $payment_service_provider_id
 * @property int|null $org_payment_service_provider_id
 * @property PaymentAccountTypeEnum $type
 * @property string $code
 * @property string $name
 * @property bool $is_accounts
 * @property array<array-key, mixed> $data
 * @property \Illuminate\Support\Carbon|null $last_used_at
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property \Illuminate\Support\Carbon|null $fetched_at
 * @property \Illuminate\Support\Carbon|null $last_fetched_at
 * @property \Illuminate\Support\Carbon|null $deleted_at
 * @property string|null $source_id
 * @property-read Collection<int, \App\Models\Helpers\Audit> $audits
 * @property-read \App\Models\SysAdmin\Group $group
 * @property-read \App\Models\Accounting\OrgPaymentServiceProvider|null $orgPaymentServiceProvider
 * @property-read Organisation $organisation
 * @property-read Collection<int, \App\Models\Accounting\PaymentAccountShop> $paymentAccountShops
 * @property-read \App\Models\Accounting\PaymentServiceProvider $paymentServiceProvider
 * @property-read Collection<int, \App\Models\Accounting\Payment> $payments
 * @property-read Collection<int, SerialReference> $serialReferences
 * @property-read \App\Models\Accounting\PaymentAccountStats|null $stats
 * @property-read \App\Models\Helpers\UniversalSearch|null $universalSearch
 * @method static \Database\Factories\Accounting\PaymentAccountFactory factory($count = null, $state = [])
 * @method static Builder<static>|PaymentAccount newModelQuery()
 * @method static Builder<static>|PaymentAccount newQuery()
 * @method static Builder<static>|PaymentAccount onlyTrashed()
 * @method static Builder<static>|PaymentAccount query()
 * @method static Builder<static>|PaymentAccount withTrashed(bool $withTrashed = true)
 * @method static Builder<static>|PaymentAccount withoutTrashed()
 * @mixin Eloquent
 */
class PaymentAccount extends Model implements Auditable
{
    use SoftDeletes;
    use HasSlug;
    use HasFactory;
    use HasHistory;
    use inOrganisation;
    use HasUniversalSearch;

    protected $casts = [
        'data' => 'array',
        'type' => PaymentAccountTypeEnum::class,
        'last_used_at' => 'datetime',
        'fetched_at' => 'datetime',
        'last_fetched_at' => 'datetime',
    ];

    protected $attributes = [
        'data' => '{}',
    ];

    protected $guarded = [];

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function getSlugOptions(): SlugOptions
    {
        return SlugOptions::create()
            ->generateSlugsFrom('code')
            ->saveSlugsTo('slug')
            ->doNotGenerateSlugsOnUpdate();
    }

    public function generateTags(): array
    {
        return [
            'accounting',
        ];
    }

    protected array $auditInclude = [
        'code',
        'name',
    ];

    public function paymentServiceProvider(): BelongsTo
    {
        return $this->belongsTo(PaymentServiceProvider::class);
    }

    public function orgPaymentServiceProvider(): BelongsTo
    {
        return $this->belongsTo(OrgPaymentServiceProvider::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function paymentAccountShops(): HasMany
    {
        return $this->hasMany(PaymentAccountShop::class);
    }

    public function stats(): HasOne
    {
        return $this->hasOne(PaymentAccountStats::class);
    }

    public function serialReferences(): MorphMany
    {
        return $this->morphMany(SerialReference::class, 'container');
    }

}
