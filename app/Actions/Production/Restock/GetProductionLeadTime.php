<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 10 Sep 2026 Malaga, Spain
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Production\Restock;

use App\Models\Production\Production;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsObject;

class GetProductionLeadTime
{
    use AsObject;

    public const DEFAULT_DAYS = 7;
    public const MIN_SAMPLES  = 5;

    /**
     * Days from putting work on the floor to having it back in the warehouse, measured from
     * this factory's own job orders. Falls back to an editable estimate while history is thin.
     *
     * @return array{days: int, source: 'measured'|'estimate', samples: int}
     */
    public function handle(Production $production): array
    {
        $measured = DB::table('job_orders')
            ->where('production_id', $production->id)
            ->whereNotNull('in_process_at')
            ->whereNotNull('received_at')
            ->where('in_process_at', '>=', now()->subYear())
            ->selectRaw('count(*) as samples,
                avg(extract(epoch from received_at - in_process_at) / 86400) as days')
            ->first();

        if ((int) $measured->samples >= self::MIN_SAMPLES) {
            return [
                'days'    => max(1, (int) round((float) $measured->days)),
                'source'  => 'measured',
                'samples' => (int) $measured->samples,
            ];
        }

        return [
            'days'    => (int) (Arr::get($production->data, 'restock.lead_time_days') ?: self::DEFAULT_DAYS),
            'source'  => 'estimate',
            'samples' => (int) $measured->samples,
        ];
    }
}
