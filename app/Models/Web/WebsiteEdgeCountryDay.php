<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 03 Nov 2026 16:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Models\Web;

use Illuminate\Database\Eloquent\Model;

/**
 * A website's storefront HTML requests on a UTC day from one visitor country, and how many the
 * Cloudflare edge copy answered. Rows are the last copy of Cloudflare's analytics: a fetched day's
 * countries replace the stored ones.
 *
 * @property int $id
 * @property int $website_id
 * @property \Illuminate\Support\Carbon $date
 * @property string $country
 * @property int $requests
 * @property int $requests_hit
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 */
class WebsiteEdgeCountryDay extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'date' => 'date',
        ];
    }
}
