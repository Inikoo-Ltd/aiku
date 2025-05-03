<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 12 May 2024 10:58:10 British Summer Time, Sheffield, UK
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\HumanResources\JobPosition\Hydrators;

use App\Actions\Traits\WithNormalise;
use App\Models\HumanResources\JobPosition;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

class JobPositionHydrateGuests implements ShouldBeUnique
{
    use AsAction;
    use WithNormalise;

    private JobPosition $jobPosition;

    public function __construct(JobPosition $jobPosition)
    {
        $this->jobPosition = $jobPosition;
    }

    public function getJobMiddleware(): array
    {
        return [(new WithoutOverlapping($this->jobPosition->id))->dontRelease()];
    }

    public function handle(JobPosition $jobPosition): void
    {
        $numberGuests         = DB::table('user_has_pseudo_job_positions')
            ->leftJoin('users', 'user_has_pseudo_job_positions.user_id', '=', 'users.id')
            ->leftJoin('guests', 'users.id', '=', 'guests.user_id')
            ->whereNotNull('guests.id')->where('user_has_pseudo_job_positions.job_position_id', $jobPosition->id)->count('guests.id');

        $numberGuestWorkTime = DB::table('user_has_pseudo_job_positions')
            ->leftJoin('users', 'user_has_pseudo_job_positions.user_id', '=', 'users.id')
            ->leftJoin('guests', 'users.id', '=', 'guests.user_id')
            ->whereNotNull('guests.id')->where('user_has_pseudo_job_positions.job_position_id', $jobPosition->id)->sum('user_has_pseudo_job_positions.share');


        $jobPosition->stats()->update(
            [
                'number_guests'           => $numberGuests,
                'number_guests_work_time' => $numberGuestWorkTime
            ]
        );
    }


}
