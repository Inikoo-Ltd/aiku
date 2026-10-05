<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 23 Sep 2026 Malaga, Spain
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Procurement;

use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\WithProcurementAuthorisation;
use App\Actions\Traits\WithExportData;
use App\Models\SysAdmin\Organisation;
use Illuminate\Validation\Rule;
use Lorisleiva\Actions\ActionRequest;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ExportOrganisationStockCoverItems extends OrgAction
{
    use WithProcurementAuthorisation;
    use WithExportData;

    /**
     * @param array<int, string> $buckets
     */
    public function handle(Organisation $organisation, array $buckets): StreamedResponse
    {
        $stockCover = GetOrganisationStockCoverBuckets::make();

        return $this->streamCsv(
            $stockCover->exportQuery($organisation, $buckets),
            [
                __('Code'),
                __('Name'),
                __('Family'),
                __('Rank'),
                __('Stock level'),
                __('Stock'),
                __('Days of cover'),
                __('Lead time days'),
                __('Supplier'),
                __('On the way'),
                __('Suggested order'),
                __('Stock value').' ('.$organisation->currency->code.')',
            ],
            'stock-levels',
            fn ($row) => [
                $row->code,
                $row->name,
                $row->family_code,
                $row->health_rank,
                $stockCover->bucketLabel($row->bucket),
                (float) $row->quantity_available,
                $row->days_of_cover !== null ? (int) $row->days_of_cover : null,
                (int) $row->lead_time_days,
                $row->supplier_code,
                (int) $row->on_the_way_po_count > 0 ? __('Yes') : __('No'),
                $row->recommended_order_quantity !== null ? (int) ceil((float) $row->recommended_order_quantity) : null,
                round((float) $row->stock_value, 2),
            ]
        );
    }

    public function prepareForValidation(ActionRequest $request): void
    {
        $this->set('cover', array_values(array_filter(explode(',', (string) $request->input('elements.cover', 'out')))));
    }

    public function rules(): array
    {
        return [
            'cover'   => ['required', 'array'],
            'cover.*' => ['string', Rule::in(array_keys(GetOrganisationStockCoverBuckets::BUCKETS))],
        ];
    }

    public function asController(Organisation $organisation, ActionRequest $request): StreamedResponse
    {
        $this->initialisation($organisation, $request);

        return $this->handle($organisation, $this->validatedData['cover']);
    }
}
