<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 05 Nov 2026 18:40:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Models\Masters;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One refresh of a competitor's dossier, kept so what changed on their website can be shown over time.
 *
 * @property int $id
 * @property int $competitor_id
 * @property \Illuminate\Support\Carbon $fetched_at
 * @property array $intel
 * @property array $changes plain words of what differs from the refresh before, with the old and new value
 * @property-read Competitor $competitor
 */
class CompetitorIntelSnapshot extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'fetched_at' => 'datetime',
            'intel'      => 'array',
            'changes'    => 'array',
        ];
    }

    public function competitor(): BelongsTo
    {
        return $this->belongsTo(Competitor::class);
    }
}
