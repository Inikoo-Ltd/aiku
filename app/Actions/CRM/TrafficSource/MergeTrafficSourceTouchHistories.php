<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 06 Aug 2026
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\CRM\TrafficSource;

use Lorisleiva\Actions\Concerns\AsAction;

class MergeTrafficSourceTouchHistories
{
    use AsAction;

    /**
     * Combines two raw touch histories into one, ordered by timestamp with exact duplicates dropped,
     * so a prospect's server-side touches (mailshot clicks) and the browser-cookie touches captured at
     * registration end up as a single journey. Touches an aggressive parse would reject are preserved
     * verbatim rather than silently lost.
     */
    public function handle(?string $first, ?string $second): ?string
    {
        $segments = collect(preg_split('/[|,]/', ($first ?? '').'|'.($second ?? '')) ?: [])
            ->map(fn (string $segment) => trim($segment))
            ->filter()
            ->unique()
            ->sortBy(fn (string $segment) => (int) $segment)
            ->values();

        return $segments->isEmpty() ? null : $segments->implode('|');
    }
}
