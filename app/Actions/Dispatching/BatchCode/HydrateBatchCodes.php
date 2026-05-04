<?php

/*
 * Author: stewicca <stewicalf@gmail.com>
 * Created: Thu, 24 Apr 2026, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Steven Wicca Alfredo
 */

namespace App\Actions\Dispatching\BatchCode;

use App\Actions\Dispatching\BatchCode\Hydrators\BatchCodeHydrateDeliveryNotes;
use App\Actions\Traits\Hydrators\WithHydrateCommand;
use App\Models\Dispatching\BatchCode;

class HydrateBatchCodes
{
    use WithHydrateCommand;

    public string $commandSignature = 'hydrate:batch_codes {organisations?*} {--ids=}';

    public function __construct()
    {
        $this->model = BatchCode::class;
    }

    public function handle(BatchCode $batchCode): void
    {
        BatchCodeHydrateDeliveryNotes::run($batchCode);
    }
}
