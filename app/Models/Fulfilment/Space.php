<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 30 Jan 2025 14:34:44 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Models\Fulfilment;

use App\Enums\Fulfilment\Space\SpaceStateEnum;
use App\Models\Billables\Rental;
use App\Models\Traits\HasHistory;
use App\Models\Traits\InFulfilmentCustomer;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use OwenIt\Auditing\Contracts\Auditable;
use Spatie\Sluggable\HasSlug;
use Spatie\Sluggable\SlugOptions;

/**
 * @property int $id
 * @property int $group_id
 * @property int $organisation_id
 * @property string|null $slug
 * @property string|null $reference
 * @property int $fulfilment_id
 * @property int $fulfilment_customer_id
 * @property int|null $rental_id
 * @property int|null $rental_agreement_clause_id
 * @property SpaceStateEnum $state
 * @property int|null $current_recurring_bill_id
 * @property \Illuminate\Support\Carbon|null $start_at
 * @property \Illuminate\Support\Carbon|null $end_at
 * @property bool $exclude_weekend
 * @property array<array-key, mixed> $data
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Helpers\Audit> $audits
 * @property-read \App\Models\Fulfilment\RecurringBill|null $currentRecurringBill
 * @property-read \App\Models\Fulfilment\Fulfilment $fulfilment
 * @property-read \App\Models\Fulfilment\FulfilmentCustomer $fulfilmentCustomer
 * @property-read \App\Models\SysAdmin\Group $group
 * @property-read \App\Models\SysAdmin\Organisation $organisation
 * @property-read Rental|null $rental
 * @property-read \App\Models\Fulfilment\RentalAgreementClause|null $rentalAgreementClause
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Space newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Space newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Space query()
 * @mixin \Eloquent
 */
class Space extends Model implements Auditable
{
    use InFulfilmentCustomer;
    use HasHistory;
    use HasSlug;

    protected $guarded = [];
    protected $casts = [
        'data' => 'array',
        'state'    => SpaceStateEnum::class,
        'start_at' => 'datetime',
        'end_at'   => 'datetime',


    ];

    protected $attributes = [
        'data'            => '{}',
    ];

    public function generateTags(): array
    {
        return ['fulfilment'];
    }

    protected array $auditInclude = [
        'reference',
        'state',
        'exclude_weekend',
        'start_at',
        'end_at',
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
            ->slugsShouldBeNoLongerThan(128);
    }

    public function rental(): BelongsTo
    {
        return $this->belongsTo(Rental::class);
    }

    public function currentRecurringBill(): BelongsTo
    {
        return $this->belongsTo(RecurringBill::class, 'current_recurring_bill_id');
    }


    public function rentalAgreementClause(): BelongsTo
    {
        return $this->belongsTo(RentalAgreementClause::class);
    }

}
