<?php

/*
 * Author: stewicca <stewicalf@gmail.com>
 * Copyright (c) 2026, Steven Wicca Alfredo
 */

namespace App\Models\Web;

use Illuminate\Database\Eloquent\Model;

/**
 * The estimated Google search traffic of a domain in one market for one month, from DataForSEO Labs:
 * organic, paid, featured snippet and local pack visits, and the keywords it is seen for.
 *
 * @property int $id
 * @property string $domain
 * @property string $country_code
 * @property string $language_code
 * @property \Illuminate\Support\Carbon $month
 * @property string $organic_traffic
 * @property int $organic_keywords
 * @property string $paid_traffic
 * @property int $paid_keywords
 * @property string $featured_snippet_traffic
 * @property string $local_pack_traffic
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SeoDomainTraffic newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SeoDomainTraffic newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SeoDomainTraffic query()
 * @mixin \Eloquent
 */
class SeoDomainTraffic extends Model
{
    protected $table = 'seo_domain_traffic';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'month' => 'date',
        ];
    }
}
