<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 24 Apr 2023 20:23:18 Malaysia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Actions\Retina\Fulfilment\StoredItems;

use App\Actions\Fulfilment\StoredItem\ImportStoredItems;
use App\Actions\RetinaAction;
use App\Actions\Traits\WithExportData;
use App\Models\Fulfilment\FulfilmentCustomer;
use App\Models\Helpers\Upload;
use Illuminate\Support\Facades\Storage;
use Lorisleiva\Actions\ActionRequest;

class ImportRetinaStoredItem extends RetinaAction
{
    /**
     * @throws \Throwable
     */
    public function handle(FulfilmentCustomer $fulfilmentCustomer, ActionRequest $request): Upload
    {
        return ImportStoredItems::run($fulfilmentCustomer, $request);
    }

    public function rules(): array
    {
        return [
            'file' => ['required', 'file', 'mimes:xlsx,csv,xls,txt'],
        ];
    }

    /**
     * @throws \Throwable
     */
    public function asController(ActionRequest $request): Upload
    {
        $this->initialisation($request);

        return $this->handle($this->fulfilmentCustomer, $request);
    }
}
