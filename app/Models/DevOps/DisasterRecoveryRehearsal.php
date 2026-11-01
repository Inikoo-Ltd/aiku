<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 01 Nov 2026 14:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Models\DevOps;

use App\Models\SysAdmin\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Cache;

class DisasterRecoveryRehearsal extends Model
{
    public const string ISSUE_CACHE_KEY = 'devops:disaster-recovery-issue';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'rehearsed_on' => 'date',
            'next_due_on'  => 'date',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public static function latestRehearsal(): ?self
    {
        return self::orderByDesc('rehearsed_on')->orderByDesc('id')->first();
    }

    public static function issue(): ?string
    {
        return Cache::remember(self::ISSUE_CACHE_KEY, 3600, function () {
            $latest = self::latestRehearsal();

            return match (true) {
                !$latest                           => 'No disaster recovery rehearsal is recorded. Run one from devops/dr/README.md and record it on the Devops dashboard.',
                $latest->next_due_on->isPast()     => 'The disaster recovery rehearsal was due on '.$latest->next_due_on->format('j M Y').'. Run it from devops/dr/README.md and record it on the Devops dashboard.',
                default                            => '',
            };
        }) ?: null;
    }
}
