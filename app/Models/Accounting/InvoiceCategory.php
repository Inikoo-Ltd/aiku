<?php

/*
 * author Arya Permana - Kirin
 * created on 28-10-2024-10h-52m
 * github: https://github.com/KirinZero0
 * copyright 2024
*/

namespace App\Models\Accounting;

use App\Enums\Accounting\InvoiceCategory\InvoiceCategoryStateEnum;
use App\Enums\Accounting\InvoiceCategory\InvoiceCategoryTypeEnum;
use App\Models\Helpers\Currency;
use App\Models\Traits\HasHistory;
use App\Models\Traits\InOrganisation;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use OwenIt\Auditing\Contracts\Auditable;
use Spatie\Sluggable\HasSlug;
use Spatie\Sluggable\SlugOptions;

/**
 *
 *
 * @property int $id
 * @property int $group_id
 * @property string $slug
 * @property string $name
 * @property InvoiceCategoryStateEnum $state
 * @property string|null $fetched_at
 * @property string|null $last_fetched_at
 * @property string|null $source_id
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property int $priority
 * @property int $currency_id
 * @property InvoiceCategoryTypeEnum $type
 * @property array<array-key, mixed> $settings
 * @property bool $show_in_dashboards
 * @property array<array-key, mixed> $data
 * @property int|null $organisation_id
 * @property string|null $colour
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Helpers\Audit> $audits
 * @property-read Currency $currency
 * @property-read \App\Models\SysAdmin\Group $group
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Accounting\Invoice> $invoices
 * @property-read \App\Models\Accounting\InvoiceCategoryOrderingIntervals|null $orderingIntervals
 * @property-read \App\Models\SysAdmin\Organisation|null $organisation
 * @property-read \App\Models\Accounting\InvoiceCategorySalesIntervals|null $salesIntervals
 * @property-read \App\Models\Accounting\InvoiceCategoryStats|null $stats
 * @method static \Illuminate\Database\Eloquent\Builder<static>|InvoiceCategory newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|InvoiceCategory newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|InvoiceCategory query()
 * @mixin \Eloquent
 */
class InvoiceCategory extends Model implements Auditable
{
    use HasSlug;
    use HasHistory;
    use InOrganisation;

    protected $casts = [
        'state'    => InvoiceCategoryStateEnum::class,
        'type'     => InvoiceCategoryTypeEnum::class,
        'data'     => 'array',
        'settings' => 'array',
    ];

    protected $attributes = [
        'data'     => '{}',
        'settings' => '{}',
    ];

    protected $guarded = [];

    public function generateTags(): array
    {
        return ['accounting'];
    }

    protected array $auditInclude = [
        'name',
        'state',
        'type',
        'settings'
    ];

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function getSlugOptions(): SlugOptions
    {
        return SlugOptions::create()
            ->generateSlugsFrom('name')
            ->saveSlugsTo('slug')
            ->doNotGenerateSlugsOnUpdate();
    }

    public function stats(): HasOne
    {
        return $this->hasOne(InvoiceCategoryStats::class);
    }

    public function salesIntervals(): HasOne
    {
        return $this->hasOne(InvoiceCategorySalesIntervals::class);
    }

    public function orderingIntervals(): HasOne
    {
        return $this->hasOne(InvoiceCategoryOrderingIntervals::class);
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }

    public function currency(): BelongsTo
    {
        return $this->belongsTo(Currency::class);
    }
}
