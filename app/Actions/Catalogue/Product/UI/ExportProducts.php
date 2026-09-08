<?php

/*
 * Author: Artha <artha@aw-advantage.com>
 * Created: Fri, 31 Jul 2026 10:12:04 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Catalogue\Product\UI;

use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\WithCatalogueAuthorisation;
use App\Actions\Traits\WithExportData;
use App\Enums\Helpers\Export\ExportTypeEnum;
use App\Exports\Catalogue\ProductsExport;
use App\Models\Catalogue\Shop;
use App\Models\SysAdmin\Organisation;
use Illuminate\Validation\Rule;
use Lorisleiva\Actions\ActionRequest;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ExportProducts extends OrgAction
{
    use WithCatalogueAuthorisation;
    use WithExportData;

    private const STREAM_THRESHOLD = 20000;

    /**
     * @throws \Throwable
     */
    public function handle(Shop $shop, array $modelData): BinaryFileResponse|StreamedResponse
    {
        $type   = $modelData['type'];
        $bucket = $modelData['bucket'] ?? 'all';
        $fields = $modelData['columns'] ?? [];

        $export = new ProductsExport($shop, $bucket, $fields, $modelData['prefix'] ?? null);

        if ($type === ExportTypeEnum::XLSX->value && $export->count() < self::STREAM_THRESHOLD) {
            return $this->export($export, 'products', $type);
        }

        return $this->streamCsv($export->dataQuery(), $export->headings(), 'products');
    }

    public function rules(): array
    {
        return [
            'type'      => ['required', 'string', Rule::in('csv', 'xlsx')],
            'bucket'    => ['sometimes', 'nullable', 'string', Rule::in('all', 'current', 'in_process', 'discontinued')],
            'prefix'    => ['sometimes', 'nullable', 'string'],
            'columns'   => ['sometimes', 'nullable', 'array'],
            'columns.*' => ['string', Rule::in(array_keys(ProductsExport::fieldDefinitions()))],
        ];
    }

    /**
     * @throws \Throwable
     */
    public function inShop(Organisation $organisation, Shop $shop, ActionRequest $request): BinaryFileResponse|StreamedResponse
    {
        $this->initialisationFromShop($shop, $request);

        return $this->handle($shop, $this->validatedData);
    }
}
