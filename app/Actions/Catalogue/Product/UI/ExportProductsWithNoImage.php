<?php

/*
 * Author: stewicca <stewicalf@gmail.com>
 * Copyright (c) 2026, Steven Wicca Alfredo
 */

namespace App\Actions\Catalogue\Product\UI;

use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\WithCatalogueAuthorisation;
use App\Actions\Traits\WithExportData;
use App\Enums\Helpers\Export\ExportTypeEnum;
use App\Exports\Catalogue\ProductsWithNoImageExport;
use App\Models\Catalogue\Shop;
use App\Models\SysAdmin\Organisation;
use Illuminate\Validation\Rule;
use Lorisleiva\Actions\ActionRequest;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ExportProductsWithNoImage extends OrgAction
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
        $fields = $modelData['columns'] ?? [];

        $export = new ProductsWithNoImageExport($shop, $fields, $modelData['prefix'] ?? null);

        if ($type === ExportTypeEnum::XLSX->value && $export->count() < self::STREAM_THRESHOLD) {
            return $this->export($export, 'products-with-no-image', $type);
        }

        return $this->streamMappedCsv($export, 'products-with-no-image');
    }

    public function rules(): array
    {
        return [
            'type'      => ['required', 'string', Rule::in('csv', 'xlsx')],
            'prefix'    => ['sometimes', 'nullable', 'string'],
            'columns'   => ['sometimes', 'nullable', 'array'],
            'columns.*' => ['string', Rule::in(array_keys(ProductsWithNoImageExport::fieldDefinitions()))],
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
