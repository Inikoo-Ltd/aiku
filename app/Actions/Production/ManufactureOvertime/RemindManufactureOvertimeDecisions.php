<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 10 Nov 2026 09:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Production\ManufactureOvertime;

use App\Actions\Production\ManufactureArtisanDay\HydrateManufactureArtisanDays;
use App\Actions\SysAdmin\User\NotifyUserOnChosenChannels;
use App\Enums\SysAdmin\User\UserNotificationEnum;
use App\Models\Production\ManufactureArtisanDay;
use App\Models\Production\Production;
use App\Models\SysAdmin\User;
use App\Notifications\ManufactureOvertimeReminderNotification;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Laravel\Nightwatch\Facades\Nightwatch;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Monday: tells the factory's managers which overtime of last week nobody has approved or rejected yet, before the pay week
 * locks at the end of the day.
 */
class RemindManufactureOvertimeDecisions
{
    use AsAction;

    public string $commandSignature = 'production:remind-overtime';

    public string $commandDescription = 'Tell factory managers on Monday which overtime of last week still needs approving or rejecting';

    public function handle(): int
    {
        $sent = 0;
        foreach (Production::whereIn('id', ManufactureArtisanDay::where('date', '>=', now()->subWeeks(3)->toDateString())->distinct()->pluck('production_id'))->with('organisation.timezone')->get() as $production) {
            $sent += $this->remind($production);
        }

        return $sent;
    }

    public function remind(Production $production): int
    {
        $timezone  = $production->organisation->timezone?->name ?? config('app.timezone');
        $weekStart = now($timezone)->startOfWeek(Carbon::MONDAY)->subWeek()->startOfDay();
        $weekEnd   = $weekStart->copy()->addDays(6);

        HydrateManufactureArtisanDays::run($production, Carbon::parse($weekStart->toDateString()), Carbon::parse($weekEnd->toDateString()));

        $days = ManufactureArtisanDay::where('production_id', $production->id)
            ->whereBetween('date', [$weekStart->toDateString(), $weekEnd->toDateString()])
            ->with(['employee', 'overtime'])
            ->orderBy('date')
            ->get()
            ->filter(fn (ManufactureArtisanDay $day) => $day->isOvertimeAwaitingDecision());
        if ($days->isEmpty()) {
            return 0;
        }

        $lines = $days->map(function (ManufactureArtisanDay $day) {
            $minutes = max(0, ($day->clocked_minutes ?? 0) - ($day->expected_minutes ?? 0));

            return sprintf('%s, %s: +%d:%02d', $day->employee?->contact_name, $day->date->format('D j M'), intdiv($minutes, 60), $minutes % 60);
        })->values()->all();

        $managers = User::permission(['org-supervisor.'.$production->organisation_id, "productions_operations.$production->id.orchestrate"])
            ->where('status', true)
            ->get();

        foreach ($managers as $manager) {
            $notification = new ManufactureOvertimeReminderNotification($production, $weekStart, $lines);
            NotifyUserOnChosenChannels::run($manager, UserNotificationEnum::MANUFACTURE_OVERTIME_REMINDER, $notification, $notification->title(), $notification->body(), $notification->url());
        }

        return $managers->count();
    }

    public function asCommand(Command $command): int
    {
        Nightwatch::dontSample();

        $command->info('Reminders sent: '.$this->handle());

        return 0;
    }
}
