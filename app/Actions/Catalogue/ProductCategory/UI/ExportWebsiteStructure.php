<?php

/*
 * Author: stewicca <stewicalf@gmail.com>
 * Copyright (c) 2026, Steven Wicca Alfredo
 */

namespace App\Actions\Catalogue\ProductCategory\UI;

use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\WithCatalogueAuthorisation;
use App\Actions\Traits\WithExportData;
use App\Enums\Catalogue\ProductCategory\ProductCategoryTypeEnum;
use App\Enums\Helpers\Export\ExportTypeEnum;
use App\Exports\Catalogue\WebsiteStructureExport;
use App\Models\Catalogue\ProductCategory;
use App\Models\Catalogue\Shop;
use App\Models\SysAdmin\Organisation;
use Illuminate\Validation\Rule;
use Lorisleiva\Actions\ActionRequest;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ExportWebsiteStructure extends OrgAction
{
    use WithCatalogueAuthorisation;
    use WithExportData;

    private const STREAM_THRESHOLD = 20000;

    /**
     * @throws \Throwable
     */
    public function handle(ProductCategory $department, array $modelData): BinaryFileResponse|StreamedResponse
    {
        $type   = $modelData['type'];
        $fields = $modelData['columns'] ?? [];

        $export   = new WebsiteStructureExport($department, $fields);
        $filename = 'website-structure-'.$department->code;

        if ($type === ExportTypeEnum::XLSX->value && $export->count() < self::STREAM_THRESHOLD) {
            return $this->export($export, $filename, $type);
        }

        return $this->streamCsv($export->dataQuery(), $export->headings(), $filename);
    }

    public function rules(): array
    {
        return [
            'type'      => ['required', 'string', Rule::in('csv', 'xlsx')],
            'columns'   => ['sometimes', 'nullable', 'array'],
            'columns.*' => ['string', Rule::in(array_keys(WebsiteStructureExport::fieldDefinitions()))],
        ];
    }

    /**
     * @throws \Throwable
     */
    public function asController(Organisation $organisation, Shop $shop, ProductCategory $department, ActionRequest $request): BinaryFileResponse|StreamedResponse
    {
        if ($department->type != ProductCategoryTypeEnum::DEPARTMENT) {
            abort(404);
        }

        $this->initialisationFromShop($shop, $request);

        return $this->handle($department, $this->validatedData);
    }
}
