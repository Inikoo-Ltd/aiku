<?php

namespace App\Actions\HumanResources\Timesheet;

use Illuminate\Support\Carbon;
use Lorisleiva\Actions\Concerns\AsAction;

class ResolveTimesheetDateRange
{
    use AsAction;

    /**
     * @return array{0: string, 1: string}
     */
    public function handle(): array
    {
        $between = request()->input('between.date');

        if (!is_string($between) || !preg_match('/^\d{8}-\d{8}$/', $between)) {
            $between = now()->startOfMonth()->format('Ymd').'-'.now()->format('Ymd');
            request()->merge(['between' => ['date' => $between]]);
        }

        [$start, $end] = explode('-', $between);

        return [
            Carbon::createFromFormat('Ymd', $start)->startOfDay()->toDateString(),
            Carbon::createFromFormat('Ymd', $end)->endOfDay()->toDateString(),
        ];
    }
}
