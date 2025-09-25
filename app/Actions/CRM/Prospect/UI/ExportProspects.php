<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 24 Apr 2023 20:23:18 Malaysia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Actions\CRM\Prospect\UI;

use App\Actions\OrgAction;
use App\Actions\Traits\WithExportData;
use App\Exports\CRM\ProspectsExport;
use App\Models\Catalogue\Shop;
use App\Models\SysAdmin\Organisation;
use Illuminate\Validation\Rule;
use Lorisleiva\Actions\ActionRequest;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ExportProspects extends OrgAction
{
    use WithExportData;

    /**
     * @throws \Throwable
     */
    public function handle(Organisation|Shop $parent, array $modelData): BinaryFileResponse
    {
        $type = $modelData['type'];

        return $this->export(new ProspectsExport($parent), 'prospects', $type);
    }

    public function rules(): array
    {
        return [
            'type' => ['required', 'string', Rule::in('csv', 'xlsx')],
        ];
    }


    /**
     * @throws \Throwable
     */
    public function asController(Organisation $organisation, ActionRequest $request): BinaryFileResponse
    {
        $this->initialisation($organisation, $request);
        return $this->handle($organisation, $this->validatedData);
    }

    /**
     * @throws \Throwable
     */
    public function inShop(Organisation $organisation, Shop $shop, ActionRequest $request): BinaryFileResponse
    {
        $this->initialisation($organisation, $request);
        return $this->handle($shop, $this->validatedData);
    }
}
