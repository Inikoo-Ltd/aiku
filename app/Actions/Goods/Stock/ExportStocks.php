<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 24 Mar 2024 13:28:47 Malaysia Time, Mexico City, Mexico
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Goods\Stock;

use App\Actions\OrgAction;
use App\Actions\Traits\WithExportData;
use App\Exports\Goods\StocksExport;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;
use Lorisleiva\Actions\Concerns\WithAttributes;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ExportStocks extends OrgAction
{
    use AsAction;
    use WithAttributes;
    use WithExportData;

    /**
     * @throws \Throwable
     */
    public function handle(array $modelData): BinaryFileResponse
    {
        $type = $modelData['type'];

        return $this->export(new StocksExport(), 'stocks', $type);
    }

    /**
     * @throws \Throwable
     */
    public function asController(ActionRequest $request): BinaryFileResponse
    {
        $this->initialisationFromGroup(group(), $request);

        return $this->handle($this->validatedData);
    }
}
