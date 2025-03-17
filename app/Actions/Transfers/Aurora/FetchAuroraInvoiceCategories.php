<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 16 Feb 2025 12:07:57 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Transfers\Aurora;

use App\Actions\Accounting\InvoiceCategory\StoreInvoiceCategory;
use App\Actions\Accounting\InvoiceCategory\UpdateInvoiceCategory;
use App\Models\Accounting\InvoiceCategory;
use App\Transfers\SourceOrganisationService;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

class FetchAuroraInvoiceCategories extends FetchAuroraAction
{
    public string $commandSignature = 'fetch:invoice_categories {organisations?*} {--s|source_id=} {--d|db_suffix=}';

    /**
     * @throws \Throwable
     */
    public function handle(SourceOrganisationService $organisationSource, int $organisationSourceId): ?InvoiceCategory
    {
        $invoiceCategoryData = $organisationSource->fetchInvoiceCategory($organisationSourceId);
        if (!$invoiceCategoryData) {
            return null;
        }


        if ($invoiceCategory = InvoiceCategory::where('source_id', $invoiceCategoryData['invoice_category']['source_id'])->first()) {
            return UpdateInvoiceCategory::make()->action(
                invoiceCategory: $invoiceCategory,
                modelData: $invoiceCategoryData['invoice_category'],
                hydratorsDelay: 60,
                strict: false,
                audit: false
            );
        }


        return StoreInvoiceCategory::make()->action(
            parent: $organisationSource->getOrganisation()->group,
            modelData: $invoiceCategoryData['invoice_category'],
            hydratorsDelay: 60,
            strict: false,
            audit: false
        );
    }


    public function getModelsQuery(): Builder
    {
        return DB::connection('aurora')
            ->table('Category Dimension')
            ->leftJoin('Invoice Category Dimension', 'Category Key', 'Invoice Category Key')
            ->select('Category Key as source_id')
            ->where('Category Branch Type', 'Head')
            ->where('Category Scope', 'Invoice')
            ->orderBy('source_id');
    }

    public function count(): ?int
    {
        return DB::connection('aurora')->table('Category Dimension')
            ->where('Category Branch Type', 'Head')
            ->where('Category Scope', 'Invoice')
            ->count();
    }
}
