<?php

/*
 * author Arya Permana - Kirin
 * created on 19-12-2024-11h-33m
 * github: https://github.com/KirinZero0
 * copyright 2024
*/

namespace App\Actions\Billables\Charge;

use App\Actions\Catalogue\Asset\Hydrators\AssetHydrateInvoicedCustomers;
use App\Actions\Catalogue\Asset\Hydrators\AssetHydrateInvoices;
use App\Actions\HydrateModel;
use App\Actions\Traits\Hydrators\WithHydrateCommand;
use App\Models\Billables\Charge;

class HydrateCharge extends HydrateModel
{
    use WithHydrateCommand;

    public string $commandSignature = 'hydrate:charges {organisations?*} {--slugs=}';

    public function __construct()
    {
        $this->model = Charge::class;
    }

    public function handle(Charge $charge): void
    {
        AssetHydrateInvoices::run($charge->asset);
        AssetHydrateInvoicedCustomers::run($charge->asset);

    }
}
