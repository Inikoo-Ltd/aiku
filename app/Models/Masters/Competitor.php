<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 01 Oct 2026 12:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Models\Masters;

use App\Enums\Masters\Competitor\CompetitorSellsToEnum;
use App\Enums\Masters\Competitor\CompetitorStatusEnum;
use App\Models\Helpers\Currency;
use App\Models\Helpers\Language;
use App\Models\SysAdmin\Group;
use App\Models\Traits\HasImage;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\MediaLibrary\HasMedia;

/**
 * A competitor's website whose prices are read every week for HELP-3605, from its product feed or
 * by searching the site (logged in with our trade account when it hides prices). Names, links and
 * logins live only here, never in the code: the repository is public.
 *
 * @property int $id
 * @property int $group_id
 * @property int $master_shop_id
 * @property string $name
 * @property string $website
 * @property CompetitorSellsToEnum $sells_to
 * @property int $currency_id
 * @property string|null $feed_url
 * @property string|null $search_url link with {query} where the search words go
 * @property string|null $login_url
 * @property string|null $username
 * @property string|null $password
 * @property array|null $cookies
 * @property CompetitorStatusEnum|null $status
 * @property string|null $last_error
 * @property \Illuminate\Support\Carbon|null $fetched_at
 * @property int $number_products
 * @property int|null $image_id a screenshot of their homepage
 * @property array|null $intel the dossier: identity, shipping, returns, payments, offers and briefing, each fact with its source page
 * @property \Illuminate\Support\Carbon|null $intel_fetched_at
 * @property string|null $intel_error
 * @property int|null $language_id the language of their website: products are matched and searched with our names in it
 * @property bool $prices_by_hand a marketplace that blocks automated visits: a person checks its prices each month and nothing is crawled
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read Currency $currency
 * @property-read Group $group
 * @property-read Language|null $language
 * @property-read MasterShop $masterShop
 * @property-read \Illuminate\Database\Eloquent\Collection<int, CompetitorProduct> $products
 * @property-read \Illuminate\Database\Eloquent\Collection<int, CompetitorIntelSnapshot> $intelSnapshots
 * @property-read \Illuminate\Database\Eloquent\Collection<int, CompetitorPriceCheck> $priceChecks
 */
class Competitor extends Model implements HasMedia
{
    use HasImage;

    protected $guarded = [];

    protected $hidden = ['feed_url', 'username', 'password', 'cookies'];

    protected function casts(): array
    {
        return [
            'sells_to'   => CompetitorSellsToEnum::class,
            'status'     => CompetitorStatusEnum::class,
            'feed_url'   => 'encrypted',
            'username'   => 'encrypted',
            'password'   => 'encrypted',
            'cookies'    => 'encrypted:array',
            'fetched_at' => 'datetime',
            'intel'      => 'array',
            'intel_fetched_at' => 'datetime',
            'prices_by_hand'   => 'boolean',
        ];
    }

    public function group(): BelongsTo
    {
        return $this->belongsTo(Group::class);
    }

    public function masterShop(): BelongsTo
    {
        return $this->belongsTo(MasterShop::class);
    }

    public function currency(): BelongsTo
    {
        return $this->belongsTo(Currency::class);
    }

    public function products(): HasMany
    {
        return $this->hasMany(CompetitorProduct::class);
    }

    public function language(): BelongsTo
    {
        return $this->belongsTo(Language::class);
    }

    public function intelSnapshots(): HasMany
    {
        return $this->hasMany(CompetitorIntelSnapshot::class);
    }

    public function priceChecks(): HasMany
    {
        return $this->hasMany(CompetitorPriceCheck::class);
    }

    public function dailyStats(): HasMany
    {
        return $this->hasMany(CompetitorDailyStat::class);
    }
}
