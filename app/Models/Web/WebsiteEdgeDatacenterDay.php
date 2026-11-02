<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 02 Nov 2026 10:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Models\Web;

use Illuminate\Database\Eloquent\Model;

/**
 * A website's storefront HTML requests on a UTC day at one Cloudflare datacenter. Each datacenter
 * keeps its own edge copy, so this is where the edge hit ratio is decided. Like the edge time
 * series, rows are the last copy of Cloudflare's analytics: upserted, never deleted.
 *
 * @property int $id
 * @property int $website_id
 * @property \Illuminate\Support\Carbon $date
 * @property string $colo
 * @property int $requests
 * @property int $requests_hit
 * @property int $requests_none
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 */
class WebsiteEdgeDatacenterDay extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'date' => 'date',
        ];
    }
}
