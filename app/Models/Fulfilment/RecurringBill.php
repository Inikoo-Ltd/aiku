<?php
/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 27 Apr 2024 08:55:35 British Summer Time, Sheffield, UK
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Models\Fulfilment;

use App\Enums\Fulfilment\RecurringBill\RecurringBillStatusEnum;
use App\Models\SysAdmin\Group;
use App\Models\SysAdmin\Organisation;
use App\Models\Traits\HasUniversalSearch;
use App\Models\Traits\InFulfilmentCustomer;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Sluggable\HasSlug;
use Spatie\Sluggable\SlugOptions;

/**
 *
 *
 * @property int $id
 * @property int $group_id
 * @property int $organisation_id
 * @property string $slug
 * @property string $reference
 * @property int $rental_agreement_id
 * @property int $fulfilment_customer_id
 * @property int $fulfilment_id
 * @property RecurringBillStatusEnum|null $status
 * @property string $start_date
 * @property string|null $end_date
 * @property string $amount
 * @property string $tax
 * @property string $total
 * @property array|null $data
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property \Illuminate\Support\Carbon|null $deleted_at
 * @property-read \App\Models\Fulfilment\Fulfilment $fulfilment
 * @property-read \App\Models\Fulfilment\FulfilmentCustomer $fulfilmentCustomer
 * @property-read Group $group
 * @property-read Organisation $organisation
 * @property-read \App\Models\Fulfilment\RentalAgreement $rentalAgreement
 * @property-read \App\Models\Fulfilment\RecurringBillStats|null $stats
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Fulfilment\RecurringBillTransaction> $transactions
 * @property-read \App\Models\Search\UniversalSearch|null $universalSearch
 * @method static \Illuminate\Database\Eloquent\Builder|RecurringBill newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|RecurringBill newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|RecurringBill onlyTrashed()
 * @method static \Illuminate\Database\Eloquent\Builder|RecurringBill query()
 * @method static \Illuminate\Database\Eloquent\Builder|RecurringBill withTrashed()
 * @method static \Illuminate\Database\Eloquent\Builder|RecurringBill withoutTrashed()
 * @mixin \Eloquent
 */
class RecurringBill extends Model
{
    use SoftDeletes;
    use HasUniversalSearch;
    use HasSlug;
    use InFulfilmentCustomer;

    protected $guarded = [];

    protected $casts = [
        'data'   => 'array',
        'status' => RecurringBillStatusEnum::class
    ];

    protected $attributes = [
        'data' => '{}',
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
            ->doNotGenerateSlugsOnUpdate()
            ->slugsShouldBeNoLongerThan(64);
    }


    public function rentalAgreement(): BelongsTo
    {
        return $this->belongsTo(RentalAgreement::class);
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(RecurringBillTransaction::class);
    }

    public function stats(): HasOne
    {
        return $this->hasOne(RecurringBillStats::class);
    }


}
