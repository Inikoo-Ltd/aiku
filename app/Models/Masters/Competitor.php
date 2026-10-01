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
use App\Models\SysAdmin\Group;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

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
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read Currency $currency
 * @property-read Group $group
 * @property-read MasterShop $masterShop
 * @property-read \Illuminate\Database\Eloquent\Collection<int, CompetitorProduct> $products
 */
class Competitor extends Model
{
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
}
