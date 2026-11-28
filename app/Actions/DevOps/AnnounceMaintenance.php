<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 28 Nov 2026 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\DevOps;

use App\Events\MaintenanceNoticeWebsocketEvent;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Lorisleiva\Actions\Concerns\AsAction;

class AnnounceMaintenance
{
    use AsAction;

    public const string CACHE_KEY = 'maintenance_notice';

    public function getCommandSignature(): string
    {
        return 'maintenance:announce {starts_at? : Start time in UTC, e.g. "2026-11-29 21:00"} {minutes? : How long it lasts} {--clear : Take the notice down}';
    }

    /**
     * @return array{starts_at: string, minutes: int}|null
     */
    public static function current(): ?array
    {
        return Cache::get(self::CACHE_KEY);
    }

    /**
     * @return array{starts_at: string, minutes: int}|null
     */
    public function handle(?Carbon $startsAt, int $minutes = 0): ?array
    {
        if (!$startsAt) {
            Cache::forget(self::CACHE_KEY);
            MaintenanceNoticeWebsocketEvent::dispatch(null);

            return null;
        }

        $notice = [
            'starts_at' => $startsAt->toIso8601String(),
            'minutes'   => $minutes,
        ];

        Cache::put(self::CACHE_KEY, $notice, $startsAt->copy()->addMinutes($minutes));
        MaintenanceNoticeWebsocketEvent::dispatch($notice);

        return $notice;
    }

    public function asCommand(Command $command): int
    {
        if ($command->option('clear')) {
            $this->handle(null);
            $command->info('Maintenance notice cleared.');

            return 0;
        }

        $startsAt = $command->argument('starts_at');
        $minutes  = (int) $command->argument('minutes');
        if (!$startsAt || $minutes < 1) {
            $command->error('Give the start time (UTC) and the minutes, or --clear.');

            return 1;
        }

        $startsAt = Carbon::parse($startsAt, 'UTC');
        if ($startsAt->copy()->addMinutes($minutes)->isPast()) {
            $command->error('That window is already over.');

            return 1;
        }

        $this->handle($startsAt, $minutes);
        $command->info("Maintenance notice: {$startsAt->toDateTimeString()} UTC for $minutes minutes.");

        return 0;
    }
}
