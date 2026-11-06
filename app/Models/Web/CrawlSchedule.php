<?php

/*
 * Author: stewicca <stewicalf@gmail.com>
 * Created: Fri, 06 Nov 2026, Bali, Indonesia
 * Copyright (c) 2026, Steven Wicca Alfredo
 */

namespace App\Models\Web;

use App\Enums\Web\Crawl\CrawlScheduleFrequencyEnum;
use App\Models\SysAdmin\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

class CrawlSchedule extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'frequency'   => CrawlScheduleFrequencyEnum::class,
            'is_active'   => 'boolean',
            'last_run_at' => 'datetime',
            'next_run_at' => 'datetime',
        ];
    }

    public function website(): BelongsTo
    {
        return $this->belongsTo(Website::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * The first time after `$after` the schedule is due, in UTC. A monthly day past the end of a
     * short month runs on that month's last day.
     */
    public function nextRunAfter(Carbon $after): Carbon
    {
        $after = $after->copy()->utc();

        $candidate = match ($this->frequency) {
            CrawlScheduleFrequencyEnum::DAILY   => $after->copy()->setTime($this->hour, 0),
            CrawlScheduleFrequencyEnum::WEEKLY  => $after->copy()->startOfWeek(Carbon::SUNDAY)->addDays((int) $this->weekday)->setTime($this->hour, 0),
            CrawlScheduleFrequencyEnum::MONTHLY => $this->dayOfMonthIn($after->copy()->startOfMonth()),
        };

        while ($candidate->lte($after)) {
            $candidate = match ($this->frequency) {
                CrawlScheduleFrequencyEnum::DAILY   => $candidate->addDay(),
                CrawlScheduleFrequencyEnum::WEEKLY  => $candidate->addWeek(),
                CrawlScheduleFrequencyEnum::MONTHLY => $this->dayOfMonthIn($candidate->copy()->startOfMonth()->addMonthNoOverflow()),
            };
        }

        return $candidate;
    }

    private function dayOfMonthIn(Carbon $monthStart): Carbon
    {
        return $monthStart->addDays(min((int) $this->day_of_month, $monthStart->daysInMonth) - 1)->setTime($this->hour, 0);
    }
}
