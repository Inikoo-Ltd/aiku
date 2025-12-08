<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 03 Oct 2025 11:36:21 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Retina\Accounting\TopUp;

use App\Actions\Accounting\TopUp\WithSingleTopUpReceipt;
use App\Actions\RetinaAction;
use App\Models\Accounting\TopUp;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;
use Lorisleiva\Actions\Concerns\WithAttributes;
use Symfony\Component\HttpFoundation\Response;

class SinglePdfTopupPdf extends RetinaAction
{
    use AsAction;
    use WithAttributes;
    use WithSingleTopUpReceipt;

    public function handle(TopUp $topUp, array $options): Response
    {
        return $this->processDataExportPdf($topUp);
    }

    public function asController(TopUp $topUp, ActionRequest $request): Response
    {
        $this->initialisation($request);

        return $this->handle($topUp, $this->validatedData);
    }
}
