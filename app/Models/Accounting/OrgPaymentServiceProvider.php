<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 09 Apr 2024 19:44:24 Central Indonesia Time, Bali Office , Indonesia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Models\Accounting;

use App\Models\SysAdmin\Group;
use App\Models\SysAdmin\Organisation;
use App\Models\Traits\HasHistory;
use App\Models\Traits\InOrganisation;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\Relations\HasOne;
use OwenIt\Auditing\Contracts\Auditable;
use Spatie\Sluggable\HasSlug;
use Spatie\Sluggable\SlugOptions;

/**
 * App\Models\Accounting\OrgPaymentServiceProvider
 *
 * @property int $id
 * @property int $group_id
 * @property int $organisation_id
 * @property int $payment_service_provider_id
 * @property string $type
 * @property string $slug
 * @property string $code
 * @property array<array-key, mixed> $data
 * @property string|null $last_used_at
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property string|null $fetched_at
 * @property string|null $last_fetched_at
 * @property string|null $deleted_at
 * @property string|null $source_id
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Accounting\PaymentAccount> $accounts
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Helpers\Audit> $audits
 * @property-read Group $group
 * @property-read Organisation $organisation
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Accounting\PaymentAccount> $paymentAccounts
 * @property-read \App\Models\Accounting\PaymentServiceProvider $paymentServiceProvider
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Accounting\Payment> $payments
 * @property-read \App\Models\Accounting\OrgPaymentServiceProviderStats|null $stats
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OrgPaymentServiceProvider newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OrgPaymentServiceProvider newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OrgPaymentServiceProvider query()
 *
 * @mixin \Eloquent
 */
class OrgPaymentServiceProvider extends Model implements Auditable
{
    use HasHistory;
    use HasSlug;
    use InOrganisation;

    protected $casts = [
        'data' => 'array',
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

    public function paymentServiceProvider(): BelongsTo
    {
        return $this->belongsTo(PaymentServiceProvider::class);
    }

    public function paymentAccounts(): HasMany
    {
        return $this->hasMany(PaymentAccount::class);
    }

    public function payments(): HasManyThrough
    {
        return $this->hasManyThrough(Payment::class, PaymentAccount::class);
    }

    public function accounts(): HasMany
    {
        return $this->hasMany(PaymentAccount::class);
    }

    public function stats(): HasOne
    {
        return $this->hasOne(OrgPaymentServiceProviderStats::class);
    }
}
