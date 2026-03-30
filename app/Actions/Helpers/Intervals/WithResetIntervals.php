<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 19 Mar 2025 00:54:20 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Helpers\Intervals;

use Lorisleiva\Actions\Concerns\AsAction;

trait WithResetIntervals
{
    use AsAction;

    protected array $intervals = [];
    protected array $doPreviousPeriods = [];

    protected function resetGroups(): void
    {
        ProcessResetIntervalsGroups::dispatch($this->intervals, $this->doPreviousPeriods);
    }

    protected function resetOrganisations(): void
    {
        ProcessResetIntervalsOrganisations::dispatch($this->intervals);
    }


    protected function resetShops(): void
    {
        ProcessResetIntervalsShops::dispatch($this->intervals, $this->doPreviousPeriods);
    }



    public function handle(): void
    {
        $this->resetGroups();
        $this->resetOrganisations();
        $this->resetShops();
    }


    public function asCommand(): int
    {
        $this->handle();

        return 0;
    }

}
