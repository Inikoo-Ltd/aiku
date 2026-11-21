<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 20 Nov 2026 16:30:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Models\Chat;

use Closure;
use Illuminate\Database\Eloquent\Model;
use Throwable;

/**
 * One run of a nightly Slack command; error is set when it stopped on a Slack error or reported failures.
 *
 * @property int $id
 * @property string $command
 * @property array<string, mixed>|null $totals
 * @property \Illuminate\Support\Carbon $started_at
 * @property \Illuminate\Support\Carbon|null $finished_at
 * @property int|null $channels
 * @property int|null $messages_read
 * @property int|null $messages_added
 * @property string|null $error
 */
class SlackSyncRun extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'started_at'  => 'datetime',
            'finished_at' => 'datetime',
            'totals'      => 'array',
        ];
    }

    /**
     * @param  Closure(): array<string, mixed>  $run
     * @return array<string, mixed>
     */
    public static function record(string $command, Closure $run): array
    {
        $syncRun = self::create(['command' => $command, 'started_at' => now()]);
        try {
            $totals = $run();
        } catch (Throwable $exception) {
            $syncRun->update(['finished_at' => now(), 'error' => $exception->getMessage()]);

            throw $exception;
        }
        $failures = $totals['failed'] ?? [];
        $syncRun->update([
            'finished_at' => now(),
            'totals'      => array_filter($totals, fn ($value) => is_scalar($value)),
            'error'       => $failures ? implode("\n", $failures) : null,
        ]);

        return $totals;
    }
}
