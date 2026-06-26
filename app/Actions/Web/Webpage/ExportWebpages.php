<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Web\Webpage;

use App\Actions\OrgAction;
use App\Actions\Traits\WithExportData;
use App\Exports\Web\WebpagesExport;
use App\Models\Catalogue\Shop;
use App\Models\Fulfilment\Fulfilment;
use App\Models\SysAdmin\Organisation;
use App\Models\Web\Website;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\WithAttributes;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ExportWebpages extends OrgAction
{
    use WithAttributes;
    use WithExportData;

    /**
     * @throws \Throwable
     */
    public function handle(Website $website, array $modelData): BinaryFileResponse
    {
        return $this->export(new WebpagesExport($website), 'webpages', $modelData['type']);
    }

    /**
     * @throws \Throwable
     */
    public function asController(Organisation $organisation, Shop $shop, Website $website, ActionRequest $request): BinaryFileResponse
    {
        $this->initialisationFromShop($shop, $request);
        $this->setRawAttributes($request->all());
        $this->validateAttributes();

        return $this->handle($website, $request->all());
    }

    /**
     * @throws \Throwable
     */
    public function inFulfilment(Organisation $organisation, Fulfilment $fulfilment, Website $website, ActionRequest $request): BinaryFileResponse
    {
        $this->initialisationFromFulfilment($fulfilment, $request);
        $this->setRawAttributes($request->all());
        $this->validateAttributes();

        return $this->handle($website, $request->all());
    }
}
