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
        ProcessResetIntervalsOrganisations::dispatch($this->intervals, $this->doPreviousPeriods);
    }

    protected function resetMasterShops(): void
    {
        ProcessResetIntervalsMasterShops::dispatch($this->intervals, $this->doPreviousPeriods);
    }

    protected function resetShops(): void
    {
        ProcessResetIntervalsShops::dispatch($this->intervals, $this->doPreviousPeriods);
    }

    protected function resetInvoiceCategories(): void
    {
        ProcessResetIntervalsInvoiceCategories::dispatch($this->intervals, $this->doPreviousPeriods);
    }

    protected function resetProducts(): void
    {
        ProcessResetIntervalsProducts::dispatch($this->intervals, $this->doPreviousPeriods);
    }

    protected function resetCharges(): void
    {
        ProcessResetIntervalsCharges::dispatch($this->intervals, $this->doPreviousPeriods);
    }

    protected function resetPlatforms(): void
    {
        ProcessResetIntervalsPlatforms::dispatch($this->intervals, $this->doPreviousPeriods);
    }

    protected function resetProductCategories(): void
    {
        ProcessResetIntervalsProductCategories::dispatch($this->intervals, $this->doPreviousPeriods);
    }

    protected function resetCollections(): void
    {
        ProcessResetIntervalsCollections::dispatch($this->intervals, $this->doPreviousPeriods);
    }

    protected function resetStocks(): void
    {
        ProcessResetIntervalsStocks::dispatch($this->intervals, $this->doPreviousPeriods);
    }

    protected function resetStockFamilies(): void
    {
        ProcessResetIntervalsStockFamilies::dispatch($this->intervals, $this->doPreviousPeriods);
    }

    protected function resetOrgStocks(): void
    {
        ProcessResetIntervalsOrgStocks::dispatch($this->intervals, $this->doPreviousPeriods);
    }

    protected function resetOrgStockFamilies(): void
    {
        ProcessResetIntervalsOrgStockFamilies::dispatch($this->intervals, $this->doPreviousPeriods);
    }

    public function handle(): void
    {
        $this->resetGroups();
        $this->resetOrganisations();
        $this->resetMasterShops();
        $this->resetShops();
        $this->resetProducts();
        $this->resetCharges();
        $this->resetPlatforms();
        $this->resetInvoiceCategories();
        $this->resetProductCategories();
        $this->resetCollections();
        $this->resetStocks();
        $this->resetStockFamilies();
    }


    public function asCommand(): int
    {
        $this->handle();

        return 0;
    }

}
